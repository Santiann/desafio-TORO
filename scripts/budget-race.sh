#!/usr/bin/env bash

# Prova de concorrência da trava de verba, do lado de fora da API.
# Os testes do PHPUnit rodam em série e nunca disputam o FOR UPDATE: eles provam a
# regra, não a corrida. Este script dispara lançamentos de verdade em paralelo e
# confere as invariantes direto no banco, que é onde um furo apareceria.
#
# Precisa do compose de pé. Uso: ./scripts/budget-race.sh

set -euo pipefail

cd "$(dirname "$0")/.."

API="${API:-http://localhost:8080}"
PARALLEL="${PARALLEL:-50}"
FITS="${FITS:-10}"

failures=0

env_value() {
    grep -E "^$1=" .env | cut -d= -f2-
}

json() {
    python3 -c "import sys,json;d=json.load(sys.stdin);print($1)"
}

sql() {
    docker compose exec -T db mysql -uroot -p"$(env_value DB_ROOT_PASSWORD)" \
        "$(env_value DB_DATABASE)" -N -e "$1" 2>/dev/null
}

check() {
    if [ "$2" = "$3" ]; then
        printf '  ok     %s: %s\n' "$1" "$3"
    else
        printf '  FALHA  %s: esperado %s, obtido %s\n' "$1" "$2" "$3"
        failures=$((failures + 1))
    fi
}

fire() {
    local prefix="$1" campaign="$2" same_id="$3" out="$4"

    seq 1 "$PARALLEL" | xargs -P "$PARALLEL" -I{} \
        curl -sS -o /dev/null -w '%{http_code}\n' -X POST "$API/sales" \
            -H "Authorization: Bearer $TOKEN" \
            -H 'Content-Type: application/json' \
            -d "{\"external_id\":\"$prefix$([ "$same_id" = yes ] || echo -{})\",
                 \"campaign_id\":$campaign,\"seller_id\":$SELLER_ID,
                 \"product_id\":$PRODUCT_ID,\"quantity\":1,\"unit_value\":10.00}" \
        > "$out"
}

TOKEN=$(curl -sS -X POST "$API/auth/login" \
    -H 'Content-Type: application/json' \
    -d "{\"email\":\"admin@toro.test\",\"password\":\"$(env_value SEED_PASSWORD)\"}" \
    | json "d['token']")

read -r PRODUCT_ID POINTS <<<"$(curl -sS "$API/products" -H "Authorization: Bearer $TOKEN" \
    | json "next(f\"{p['id']} {p['points_per_unit']}\" for p in d['data'] if p['active'] and p['points_per_unit'] > 0)")"

SELLER_ID=$(curl -sS "$API/sellers" -H "Authorization: Bearer $TOKEN" | json "d['data'][0]['id']")

starts_at=$(date -d '-1 hour' '+%Y-%m-%d %H:%M:%S')
ends_at=$(date -d '+1 day' '+%Y-%m-%d %H:%M:%S')
stamp=$(date +%s)

new_campaign() {
    curl -sS -X POST "$API/campaigns" \
        -H "Authorization: Bearer $TOKEN" \
        -H 'Content-Type: application/json' \
        -d "{\"name\":\"$1\",\"budget_total\":$2,\"starts_at\":\"$starts_at\",\"ends_at\":\"$ends_at\"}" \
        | json "d['id']"
}

tmp=$(mktemp -d)
trap 'rm -rf "$tmp"' EXIT

budget=$((POINTS * FITS))
tight=$(new_campaign "Corrida de verba $stamp" "$budget")

printf '\nverba curta: %s lançamentos paralelos de %s pontos numa campanha de %s\n' \
    "$PARALLEL" "$POINTS" "$budget"

fire "RACE-$stamp" "$tight" no "$tmp/tight"

check "vendas aceitas (201)" "$FITS" "$(grep -c '^201$' "$tmp/tight" || true)"
check "vendas rejeitadas (422)" "$((PARALLEL - FITS))" "$(grep -c '^422$' "$tmp/tight" || true)"
check "budget_used" "$budget" "$(sql "SELECT budget_used FROM campaigns WHERE id = $tight;")"
check "budget_used = SUM(credit) - SUM(debit)" "$budget" \
    "$(sql "SELECT COALESCE(SUM(CASE WHEN type = 'credit' THEN points ELSE -points END), 0) FROM wallet_entries WHERE campaign_id = $tight;")"
check "créditos = vendas aprovadas" "$FITS" \
    "$(sql "SELECT COUNT(*) FROM wallet_entries WHERE campaign_id = $tight AND type = 'credit';")"
check "vendas aprovadas" "$FITS" \
    "$(sql "SELECT COUNT(*) FROM sales WHERE campaign_id = $tight AND status = 'approved';")"

loose=$(new_campaign "Corrida de idempotência $stamp" "$((POINTS * PARALLEL))")

printf '\nmesmo external_id: %s lançamentos paralelos com verba de sobra\n' "$PARALLEL"

fire "DUP-$stamp" "$loose" yes "$tmp/dup"

check "um único 201" "1" "$(grep -c '^201$' "$tmp/dup" || true)"
check "duplicatas em 200" "$((PARALLEL - 1))" "$(grep -c '^200$' "$tmp/dup" || true)"
check "vendas gravadas" "1" "$(sql "SELECT COUNT(*) FROM sales WHERE campaign_id = $loose;")"
check "créditos gravados" "1" \
    "$(sql "SELECT COUNT(*) FROM wallet_entries WHERE campaign_id = $loose AND type = 'credit';")"
check "budget_used" "$POINTS" "$(sql "SELECT budget_used FROM campaigns WHERE id = $loose;")"

printf '\nnenhuma campanha do banco com verba estourada, negativa ou fora do ledger\n'
check "campanhas inconsistentes" "0" "$(sql "
    SELECT COUNT(*) FROM campaigns c WHERE c.budget_used > c.budget_total
       OR c.budget_used <> COALESCE((SELECT SUM(CASE WHEN type = 'credit' THEN points ELSE -points END)
                                     FROM wallet_entries WHERE campaign_id = c.id), 0);")"

if [ "$failures" -eq 0 ]; then
    # A limpeza só roda no verde, de propósito: se uma invariante caiu, as linhas que
    # provam a falha são justamente o que você quer abrir no banco.
    sql "DELETE FROM wallet_entries WHERE campaign_id IN ($tight, $loose);
         DELETE FROM sales WHERE campaign_id IN ($tight, $loose);
         DELETE FROM campaigns WHERE id IN ($tight, $loose);"

    printf '\ntodas as invariantes de pé, campanhas do teste removidas\n'
else
    printf '\n%s invariante(s) violada(s), campanhas %s e %s mantidas para inspeção\n' \
        "$failures" "$tight" "$loose"
fi

exit "$failures"
