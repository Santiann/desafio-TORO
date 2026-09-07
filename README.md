# Vendeu, Ganhou

Plataforma enxuta de incentivo de vendas. O admin cadastra produtos e campanhas com
verba limitada e lança vendas; o motor de pontuação calcula os pontos, respeita a verba
e credita na carteira do vendedor. Só pontos, sem saque nem dinheiro real.

PHP 8.3 puro com PDO, MySQL 8.4, React com Vite e TypeScript, tudo em Docker.

Repositório: <https://github.com/Santiann/desafio-TORO>

## Como subir

Precisa de Docker com Compose v2. Nada de PHP, Composer ou Node na máquina.

```bash
git clone https://github.com/Santiann/desafio-TORO.git vendeu-ganhou
cd vendeu-ganhou
make setup
make up
```

`make setup` cria o `.env` gerando os segredos na hora, o que tira o único passo manual
que trava a primeira execução: o `JWT_SECRET` precisa de 32 bytes ou mais, senão a API
recusa a subir. Ao final, `make up` imprime as portas e a senha de login. `make help`
lista o resto (`test`, `race`, `reset`, `logs`, `seed`, `creds`).

Sem `make`, é o caminho longo: `cp .env.example .env`, trocar os placeholders à mão
(`openssl rand -hex 32` para o `JWT_SECRET`, e `SEED_PASSWORD` é a senha com que você
entra no frontend) e `docker compose up --build`.

A primeira subida demora, porque o MySQL inicializa o datadir e o healthcheck só libera
a API quando o banco responde. Frontend em <http://localhost:5173>, API em
<http://localhost:8080>, MySQL em `127.0.0.1:3306` (loopback só, para você abrir um
cliente SQL sem publicar o banco). As portas saem do `.env` se alguma estiver ocupada.

Para recomeçar do zero: `make reset`.

## Credenciais

Todos com a senha do `SEED_PASSWORD`.

- `admin@toro.test` - admin
- `ana@toro.test`, `bruno@toro.test`, `carla@toro.test` - sellers

O seed cria quatro produtos, uma campanha ativa com verba de 10000 pontos e cinco
vendas de exemplo, uma delas já cancelada. As vendas existem para a carteira não nascer
vazia: entrando como a Ana logo depois de subir já tem saldo e extrato, e o do Bruno
mostra um crédito e o débito do estorno. Elas passam pelo `ScoringService`, não por
`INSERT` direto, então a verba e o ledger do seed saem das mesmas regras da API.

Tudo é idempotente: subir de novo não duplica nada.

## Schema

Migrations numeradas em `backend/migrations`, aplicadas em ordem pelo `Migrator`, que
registra o que já rodou numa tabela `migrations`. O entrypoint do container `api` chama
`bin/migrate.php` e `seeds/seed.php` antes do php-fpm subir. Para adicionar uma tabela,
crie `006_algo.sql` e reinicie a API.

## O motor de pontuação

Lançar uma venda é uma transação só, em `ScoringService::registerSale`. A campanha é
travada com `SELECT ... FOR UPDATE` antes de qualquer coisa, o que serializa dois
lançamentos simultâneos e impede que ambos leiam a mesma verba disponível. Depois vêm
as validações (campanha ativa e vigente, produto ativo, `seller_id` com papel de
seller), o cálculo `pontos = quantity * points_per_unit`, o insert da venda, o crédito
no ledger e o `UPDATE` da verba. Esse `UPDATE` carrega `WHERE budget_used + ? <=
budget_total` como segunda barreira: se não afetar nenhuma linha, a transação volta
atrás inteira.

No cancelamento a venda é lida antes da campanha, mas essa leitura é um consistent read
do InnoDB e não adquire lock. O primeiro lock de escrita continua sendo o da campanha
nos dois caminhos, e é essa ordem igual que evita deadlock entre uma venda e um
cancelamento concorrentes. O estorno lê os pontos do lançamento de crédito, nunca de
`points_per_unit`: o produto pode ter sido editado depois da venda, e recalcular
devolveria à campanha um valor diferente do que saiu dela.

A idempotência é do banco, não de um `SELECT` prévio. `external_id` é `UNIQUE`, e o
segundo lançamento estoura violação de chave única, que vira rollback e resposta 200 com
`"duplicate": true`. Não existe janela entre checar e inserir. Cancelar duas vezes segue
o mesmo desenho: o `UPDATE` só casa com vendas ainda `approved`, então o segundo pedido
responde `"already_canceled": true` sem gerar débito novo.

O saldo é sempre `SUM(credit) - SUM(debit)` lido na hora. Não há coluna de saldo para
desencontrar do extrato.

## Estouro de verba: a venda é rejeitada inteira

Se os pontos não cabem no que sobrou, a API responde 422 com `budget_exceeded` e nada é
gravado.

Preferi isso a creditar só o que cabe porque o vendedor confere os pontos contra a venda
que fez: um crédito parcial e silencioso quebra essa conferência e vira reclamação, não
economia. Rejeitar também devolve a decisão para quem tem contexto, e como nada
persiste, o `external_id` continua livre para reenviar depois de ajustar a verba.
Creditar parcial seria irreversível, já que não existe endpoint para completar o crédito
depois.

O preço é uma campanha poder terminar com sobra que não dá para nenhuma venda grande.
Achei melhor sobrar verba do que pagar alguém pela metade sem avisar.

## Segurança

JWT HS256 com segredo vindo do ambiente e algoritmo fixo no decode, porque ler o `alg`
do header é como se monta um algorithm confusion. Uma hora de validade, sem tolerância.
Identidade e papel saem do token, e um `role` ou `seller_id` mandado no body é ignorado.
No login o `password_verify` roda mesmo quando o email não existe, contra um hash fixo,
para o tempo de resposta não denunciar quem está cadastrado; a falha é sempre
"credenciais inválidas".

Nenhum valor do cliente entra em SQL sem placeholder, com `EMULATE_PREPARES` em `false`
para o prepare ser do servidor. Isso vale para `LIMIT` e `OFFSET`, bindados como
inteiro. As poucas consultas que usam `query()` são as que não recebem parâmetro nenhum.
O filtro de vendas monta o `WHERE` a partir de trechos constantes, nunca de texto do
cliente. Nenhum endpoint itera o body para montar `INSERT` ou `UPDATE`, o que fecha a
porta de mass assignment. Erro não tratado vira 500 genérico, com SQL e stack trace só
no log.

Um seller pedindo a carteira de outro recebe 404, não 403, porque 403 confirmaria que
aquele vendedor existe.

Três coisas ficaram de fora conscientemente. **Não há revogação de JWT**: sair da conta
é evento só de cliente, e um token copiado antes vale até expirar. O que contém o
estrago é a validade curta. **O token fica em `localStorage`**, o que expõe a XSS; um
cookie `httpOnly` fecharia essa porta mas abriria CSRF, e a superfície de XSS aqui é
pequena (React escapa por padrão e não há `dangerouslySetInnerHTML` no projeto). Em
produção eu inverteria. **Não há rate limit no login**: nada impede alguém de tentar dez
mil senhas, e o bcrypt só segura a taxa por ser lento.

## Rotas

```
GET    /health                      publico
POST   /auth/login                  publico     { email, password } -> { token, user }

GET    /products                    admin
POST   /products                    admin
PUT    /products/{id}               admin
DELETE /products/{id}               admin       inativa, nao apaga
GET    /campaigns                   admin       traz budget_used e budget_total
POST   /campaigns                   admin
POST   /campaigns/{id}/close        admin       idempotente
GET    /sellers                     admin       id, nome, email e saldo
GET    /sales                       admin       paginada, filtra campanha/vendedor/situacao
POST   /sales                       admin
POST   /sales/{external_id}/cancel  admin

GET    /me/wallet                   seller      saldo e extrato paginado do token
```

Erro sempre no mesmo formato, com `fields` só quando a falha é de validação:

```json
{"error":{"code":"validation_failed","message":"dados inválidos","fields":{"quantity":"deve estar entre 1 e 1000000"}}}
```

Seller em rota de admin recebe 403, e o admin em `/me/wallet` também, porque a carteira é
do vendedor. Sem token é 401. O frontend trata os dois: 401 limpa a sessão e volta ao
login, 403 mostra "sem permissão".

O `requests.http` na raiz cobre todos os endpoints e os casos de erro, encadeando os
tokens do login.

## Testes

```bash
make test        # ou: docker compose exec api php vendor/bin/phpunit
```

São 24 testes de integração contra o MySQL do compose, cobrindo o motor e a carteira:
verba consumida e devolvida, rejeição por estouro, `external_id` creditado uma vez só,
cancelamento repetido sem débito novo, estorno lendo o ledger em vez do produto atual,
produto de zero ponto, campanha fechada ou fora de vigência, os filtros da listagem, e a
carteira não enxergando entrada alheia. Eles limpam os próprios dados no `tearDown`.

Só que rodam em série e nunca disputam o `FOR UPDATE`. Quem cobre a corrida é o script:

```bash
make race        # ou: ./scripts/budget-race.sh
```

Ele dispara 50 lançamentos em paralelo com `xargs -P` duas vezes, numa campanha que
comporta 10 e depois todos com o mesmo `external_id`, e confere no banco que a verba
parou no teto, que ela bate com `SUM(credit) - SUM(debit)`, e que o id repetido virou um
crédito só. Limpa o que criou quando passa; mantém quando falha, que é quando você quer
olhar o banco.

## O que ficou de fora

Não há import de vendas por CSV. O admin lança uma venda por vez, pelo formulário.

## Com mais tempo

**Fila para importação em massa.** Dez mil vendas em série não cabem num request, e cada
uma pega o lock da campanha. Receberia o arquivo, devolveria 202 com um id de lote e
deixaria um worker consumindo, usando o `external_id` de cada linha como chave de
idempotência para o lote inteiro ser seguro de reprocessar.

**Cache do saldo.** Hoje todo `GET /me/wallet` faz um `SUM` no ledger. Guardaria o saldo
materializado, escrito na mesma transação do lançamento, com o ledger seguindo como
fonte da verdade e um job de reconciliação. Invalidado por evento, nunca por TTL.

**Rate limit no login**, no nginx, para a tentativa nem chegar ao bcrypt.

**Rotação do segredo do JWT**, assinando com `kid` e mantendo um conjunto de chaves
válidas, para trocar o segredo virar rotina em vez de incidente.

**Teste de carga em cima do `budget-race.sh`**, rodando em CI e subindo a concorrência
até achar onde o `FOR UPDATE` começa a estourar timeout. Hoje eu não sei esse número.
