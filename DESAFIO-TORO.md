# Desafio Técnico — Desenvolvedor(a) Pleno

## Plataforma de Incentivo "Vendeu, Ganhou"

Você vai construir uma versão enxuta de uma plataforma de incentivo de vendas.
A ideia é simples: **o admin cadastra produtos e campanhas com verba limitada,
lança vendas (e cancelamentos), um motor de pontuação calcula os pontos e publica
na carteira do vendedor.** Não há saque nem dinheiro real — a carteira guarda
apenas **pontos**.

O objetivo do desafio é ver como você modela um domínio real, escreve backend em
PHP sem muleta de framework, protege os dados (auth + ACL) e entrega algo que
**roda de verdade** com um comando.

---

## 1. Stack obrigatória

| Camada | Tecnologia |
|---|---|
| Backend | **PHP 8.x puro** — sem Laravel/Symfony. Router próprio ou micro-lib de rota é OK. Acesso a banco via **PDO** com prepared statements. |
| Banco | **MySQL 8** (ou MariaDB). |
| Frontend | **React** (Vite ou CRA). Pode usar TypeScript. |
| Infra | **Docker + docker-compose** — sobe backend, banco e frontend. |
| Auth | **JWT** (pode usar `firebase/php-jwt`). Senha com hash (`password_hash`). |

> Não use um framework full-stack PHP. Queremos ver você resolver roteamento,
> validação e persistência "na unha". Bibliotecas pontuais (JWT, dotenv, uuid)
> são permitidas.

---

## 2. Domínio e regras de negócio

### Papéis (ACL)

- **admin** — configura tudo e lança vendas.
- **seller** (vendedor) — só enxerga a **própria** carteira e extrato.

### Entidades mínimas

- **users** — `id, name, email (único), password_hash, role (admin|seller), created_at`.
- **products** — `id, name, sku (único), points_per_unit, active, created_at`.
- **campaigns** — `id, name, budget_total, budget_used, starts_at, ends_at, status (active|closed), created_at`.
- **sales** — `id, external_id (único), campaign_id, seller_id, product_id, quantity, unit_value, status (approved|canceled), created_at`.
- **wallet_entries** — extrato/ledger: `id, seller_id, campaign_id, sale_id, type (credit|debit), points, description, created_at`.

O **saldo** da carteira é a soma do ledger (`SUM(credit) - SUM(debit)`), não um
campo mutável solto. (Se quiser cachear o saldo, mantenha a fonte da verdade no
ledger.)

### Motor de pontuação — o coração do desafio

Ao lançar uma **venda aprovada**:

1. Calcule `pontos = quantity * product.points_per_unit`.
2. **Verba (budget):** a campanha tem `budget_total`. Se `budget_used + pontos`
   ultrapassar `budget_total`, a venda **não pode** creditar além do limite.
   Defina e **documente** sua regra (rejeitar a venda inteira **ou** creditar só
   o que cabe — escolha uma e justifique no README).
3. Se couber, **credite** os pontos na carteira do vendedor (uma entrada
   `credit` no ledger) e **incremente** `budget_used`.

Ao lançar um **cancelamento** (a venda vira `canceled`):

1. **Reverta** os pontos daquela venda: entrada `debit` no ledger.
2. **Devolva** a verba: decremente `budget_used`.

### Regras que serão testadas

- **Idempotência:** `sales.external_id` é único. Lançar a mesma venda duas vezes
  **não** pode pontuar em dobro.
- **Cancelar o que não existe / já cancelado** não pode quebrar nem duplicar
  estorno.
- **Verba nunca fica negativa** e `budget_used` nunca passa de `budget_total`.
- **Consistência:** crédito de venda + atualização de verba devem ser atômicos
  (transação). Uma falha no meio não pode deixar verba debitada sem ponto
  creditado (ou vice-versa).

---

## 3. Segurança e ACL (essencial)

- **Autenticação** via JWT no header `Authorization: Bearer <token>`.
- **Autorização por papel:** rotas de admin (CRUD de produto/campanha, lançar
  venda/cancelamento) **exigem** `role = admin`. Um seller batendo nelas recebe
  **403**.
- **Ownership:** o seller só acessa a **própria** carteira/extrato. Tentar ler a
  carteira de outro vendedor → **403/404** (não vaze dados de terceiros).
- **Senhas** com `password_hash` / `password_verify`. Nunca em texto puro.
- **SQL sempre com prepared statements** — nada de concatenar input no SQL.
- **Validação de input** no servidor (tipos, obrigatórios, valores negativos).
- Não confie em nada que venha do cliente para decidir papel/identidade — isso
  vem do token, não do body.

---

## 4. Frontend (React)

Não precisa ser bonito — precisa ser **funcional e claro**.

- **Login** (email + senha → guarda o token).
- **Admin:**
  - CRUD de **produtos**.
  - Criar **campanha** com verba e período; ver `budget_used / budget_total`.
  - **Lançar venda** e **cancelamento** (formulário simples; bônus: importar CSV).
- **Seller:**
  - Ver **saldo** da carteira e o **extrato** (ledger) com créditos/débitos.

Trate os estados de erro (401 → volta pro login; 403 → "sem permissão").

---

## 5. Entregáveis

1. **Repositório Git** (link compartilhado comigo).
2. **`docker-compose up`** sobe tudo: backend, MySQL e frontend. Diga as portas.
3. **`README.md`** com:
   - Como rodar do zero (pré-requisitos, comandos).
   - Credenciais de **seed** (1 admin + ao menos 2 sellers).
   - Como o **schema** é criado (migration/script SQL rodado no boot).
   - Sua **decisão** sobre a regra de verba estourada (item 2.2).
   - O que você faria diferente com mais tempo.
4. **Seed inicial**: admin, sellers, alguns produtos e 1 campanha — pra dar pra
   testar sem cadastrar tudo à mão.
5. **Coleção de requests** (Insomnia/Postman/`.http`) ou `curl`s no README.

---

## 6. Contrato de API sugerido (guia, não obrigatório)

Você pode ajustar nomes; isto é só pra orientar o escopo.

```
POST   /auth/login                 { email, password } -> { token }

# admin
POST   /products                   cria produto
GET    /products                   lista
PUT    /products/{id}              edita
DELETE /products/{id}              remove/inativa

POST   /campaigns                  cria campanha (budget, período)
GET    /campaigns                  lista (com budget_used)

POST   /sales                      lança venda  { external_id, campaign_id, seller_id, product_id, quantity, unit_value }
POST   /sales/{external_id}/cancel cancela venda

# seller
GET    /me/wallet                  saldo + extrato do vendedor logado
```

---

## 7. Critérios de avaliação

| Peso | O que olhamos |
|---|---|
| **30%** | **Motor de pontuação correto**: verba respeitada, cancelamento estorna, idempotência, atomicidade (transação). |
| **20%** | **Segurança e ACL**: JWT, papel, ownership, prepared statements, validação. |
| **20%** | **Qualidade do código**: organização, nomes, separação de responsabilidades, legibilidade. |
| **15%** | **Roda fácil**: `docker-compose up` funciona, README claro, seed presente. |
| **15%** | **Frontend**: fluxos completos (login, admin, seller), tratamento de erro. |

---

## 8. Diferenciais (bônus — não obrigatórios)

- Testes automatizados (nem que seja do motor de pontuação).
- Import de vendas via **CSV**.
- Paginação e filtros nas listagens.
- Trava de concorrência na verba (dois lançamentos simultâneos não furam o
  budget — `SELECT ... FOR UPDATE` ou versão/optimistic lock).
- Log/auditoria de quem lançou cada venda.
- Um `Makefile` ou script `setup.sh`.

---

## 9. Restrições e prazo

- **Sem saque/pagamento real** — só pontos na carteira.
- **Sem framework full-stack PHP.**
- Escopo pensado para **~8 a 12 horas** de trabalho. Não precisa terminar os
  bônus; preferimos o **núcleo bem feito** a tudo pela metade.
- Prazo de entrega: **[definir — ex: 5 dias corridos]**.

---

## 10. Como entregar

Suba num repositório Git (GitHub/GitLab/Bitbucket) e compartilhe o link.
Deixe o `README.md` na raiz. Se algo não ficou pronto, **escreva no README** o
que faltou e por quê — honestidade conta mais que fingir completude.

Boa sorte. 🚀
