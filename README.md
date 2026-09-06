# Vendeu, Ganhou

Plataforma enxuta de incentivo de vendas. O admin cadastra produtos e campanhas com
verba limitada, lança vendas e cancelamentos; o motor de pontuação calcula os pontos,
respeita a verba da campanha e publica o resultado na carteira do vendedor. A carteira
guarda apenas pontos, não há saque nem dinheiro real.

Backend em PHP 8.3 puro (router, validação e persistência escritos na mão, PDO com
prepared statements), MySQL 8.4, frontend em React com Vite e TypeScript. Tudo sobe
com `docker compose up`.

## Como subir do zero

Você precisa de Docker com o plugin Compose v2 (`docker compose version`). Nada de PHP,
Composer ou Node na máquina: tudo roda dentro dos containers.

```bash
git clone <url-do-repositorio> vendeu-ganhou
cd vendeu-ganhou
cp .env.example .env
```

Abra o `.env` e troque os placeholders. Os três que importam:

- `JWT_SECRET` precisa de 32 bytes ou mais, senão a API recusa a subir. Gere com
  `openssl rand -hex 32`.
- `DB_PASSWORD` e `DB_ROOT_PASSWORD` são as senhas do MySQL, escolha o que quiser.
- `SEED_PASSWORD` é a senha que todos os usuários do seed vão receber. É com ela que
  você entra no frontend.

Depois:

```bash
docker compose up --build
```

A primeira subida demora: o MySQL inicializa o datadir e o healthcheck só libera a API
quando o banco responde. O container `api` roda as migrations e o seed no entrypoint,
então quando ele aparece no log já tem esquema e dados.

O frontend fica em <http://localhost:5173> e é por onde você usa o sistema. A API fica
em <http://localhost:8080> (nginx na frente do php-fpm) e responde `GET /health`. O
MySQL fica em `127.0.0.1:3306`, exposto só no loopback para você conseguir abrir um
cliente SQL sem publicar o banco na rede. As portas saem de `WEB_PORT`, `API_PORT` e
`DB_PORT_HOST` no `.env` se alguma dessas já estiver ocupada.

Para começar de novo do zero, com banco vazio: `docker compose down -v && docker compose up --build`.

## Credenciais do seed

Todos usam a senha que você colocou em `SEED_PASSWORD`.

- `admin@toro.test` - Admin Toro, papel `admin`, id 1
- `ana@toro.test` - Ana Souza, papel `seller`, id 2
- `bruno@toro.test` - Bruno Lima, papel `seller`, id 3
- `carla@toro.test` - Carla Dias, papel `seller`, id 4

O seed também cria quatro produtos (ids 1 a 4, de 5 a 25 pontos por unidade) e uma
campanha ativa, id 1, com `budget_total` de 10000 pontos e vigência de ontem até daqui
a 89 dias. Os ids acima valem em banco novo, e são eles que você digita no formulário
de lançar venda.

O seed é idempotente: ele procura por email, sku e nome antes de inserir, então subir o
compose de novo não duplica nada nem sobrescreve o que você criou.

## Como o schema é criado

Não existe dump de SQL rodado pelo entrypoint do MySQL. O schema vem de migrations
versionadas em `backend/migrations`, arquivos `.sql` numerados, aplicados em ordem
alfabética pelo `Migrator` (`backend/src/Infrastructure/Migrator.php`). Ele cria a
tabela `migrations`, lê o que já foi aplicado e roda só o que falta, registrando cada
arquivo executado.

Quem dispara isso é o entrypoint do container `api`, antes do php-fpm subir:

```sh
php /var/www/html/bin/migrate.php
php /var/www/html/seeds/seed.php
```

Como as duas etapas são idempotentes, o container pode reiniciar quantas vezes quiser.
Para adicionar uma tabela basta criar `006_algo.sql` e reiniciar a API.

## O motor de pontuação

Lançar uma venda aprovada é uma transação só, em `ScoringService::registerSale`. A
campanha é travada com `SELECT ... FOR UPDATE`, o que serializa dois lançamentos
simultâneos na mesma campanha e impede que ambos leiam a mesma verba disponível e
furem o budget. Só depois disso o serviço valida que a campanha está ativa e dentro da
vigência, que o produto existe e está ativo e que o `seller_id` é mesmo um usuário com
papel `seller`. Calcula `pontos = quantity * points_per_unit`, insere a venda, grava a
entrada `credit` no ledger e incrementa `budget_used`. O `UPDATE` da verba carrega
`WHERE budget_used + ? <= budget_total` como segunda barreira: se ele não afetar
nenhuma linha, a transação inteira volta atrás.

O cancelamento trava a campanha primeiro, na mesma ordem do lançamento. Ordem de lock
igual nos dois caminhos é o que evita deadlock entre uma venda e um cancelamento
concorrentes. O estorno lê os pontos da entrada de crédito no ledger, não de
`products.points_per_unit`: o produto pode ter sido editado depois da venda, e
recalcular devolveria à campanha um valor diferente do que foi debitado dela.

A idempotência é do banco, não de uma consulta prévia. `sales.external_id` é `UNIQUE`,
e o segundo lançamento com o mesmo id estoura violação de chave única, que o serviço
converte em rollback e devolve a venda que já existia com `"duplicate": true` e status
200. Não há janela entre um `SELECT` de checagem e o `INSERT`. Cancelar duas vezes tem
o mesmo formato: o `UPDATE` de cancelamento só casa com vendas ainda `approved`, então
o segundo pedido não gera débito novo e responde `"already_canceled": true`. Cancelar
uma venda que não existe é 404 e não escreve nada.

O saldo da carteira é sempre `SUM(credit) - SUM(debit)` sobre `wallet_entries`, lido na
hora. Não existe coluna de saldo para desencontrar do extrato.

## A decisão sobre estouro de verba

**A venda é rejeitada inteira.** Se `pontos > budget_total - budget_used`, a API
responde 422 com o código `budget_exceeded` e uma mensagem que diz quanto a venda vale
e quanto sobrou na campanha. Nada é gravado: nem a venda, nem entrada no ledger, nem
alteração de verba.

Escolhi rejeitar em vez de creditar só o que cabe por três razões. A primeira é de
domínio: numa plataforma de incentivo, o vendedor confere os pontos contra a venda que
ele fez. Um crédito parcial e silencioso quebra essa conferência - a venda diz 300
pontos, a carteira mostra 90, e ninguém no fluxo explica a diferença. Isso vira
reclamação, não economia de verba. A segunda é de consistência: com crédito parcial, a
relação `pontos = quantity * points_per_unit` deixa de valer para as linhas do ledger,
e toda reconciliação futura entre venda e extrato precisa carregar a exceção. A
terceira é operacional: rejeitar devolve uma decisão para quem tem contexto. O admin vê
a mensagem, aumenta o `budget_total`, fecha a campanha ou lança a venda em outra, e
reenvia.

Esse reenvio funciona justamente porque a rejeição não persiste nada: o `external_id`
continua livre, e o mesmo lançamento pode ser repetido depois de ajustar a verba sem
esbarrar na idempotência. Creditar parcial, em contraste, é irreversível pela API -
não há endpoint para "completar" o crédito depois.

O efeito colateral aceito é que uma campanha pode terminar com sobra de verba que não
dá para nenhuma venda grande. Considerei melhor sobrar verba do que pagar um vendedor
pela metade sem avisar.

## Segurança

O que está implementado: JWT HS256 com segredo de 32 bytes ou mais vindo do ambiente,
com o algoritmo fixo no código na hora de decodificar (ler o `alg` do header do token é
como se monta um algorithm confusion). As claims são `sub`, `role`, `iat` e `exp`, com
uma hora de validade e sem tolerância. Identidade e papel saem sempre do token; um
`role` ou `seller_id` que venha no body de rota de seller é ignorado. Senha com
`password_hash` e `PASSWORD_DEFAULT`, e o `password_verify` roda mesmo quando o email
não existe, contra um hash fixo, para o tempo de resposta não denunciar quais emails
estão cadastrados. A resposta de falha é sempre "credenciais inválidas", sem distinguir
email de senha.

Toda query usa prepared statement com `PDO::ATTR_EMULATE_PREPARES => false`, inclusive
`LIMIT` e `OFFSET`, que são bindados como inteiro depois do cast em vez de
interpolados. Nenhum endpoint itera o body para montar `INSERT` ou `UPDATE`: cada um
declara sua lista de campos, o que fecha a porta de mass assignment que transformaria
um seller em admin. Validação é whitelist com tipo, obrigatoriedade e limite, e a
rejeição volta 422 com um mapa de campo para mensagem. Erro não tratado vira 500
genérico com `{"error":{"code":"internal_error"}}`; SQL, stack trace e nome de tabela
ficam no log. O container do PHP não roda como root, `display_errors` está desligado na
imagem e o MySQL só escuta em `127.0.0.1`.

Ownership da carteira responde 404, e não 403, quando um seller pede a carteira de
outro. 403 confirmaria que aquele vendedor existe.

Três decisões conscientes de deixar de fora, que num sistema real eu não deixaria:

**Não há revogação de JWT.** Sem blocklist, sem refresh token, sem versão de sessão no
banco. Sair da conta é um evento só de cliente: o frontend joga o token fora, mas um
token copiado antes disso continua válido até expirar. O que contém o estrago é a
validade curta de uma hora. A correção real seria um `jti` por token e uma blocklist em
memória compartilhada consultada a cada request, ou um par access token curto mais
refresh token com rotação e detecção de reuso. As duas custam infraestrutura de estado
que o escopo do desafio não pedia, e meia revogação (um blocklist em memória de
processo, por exemplo) é pior que nenhuma, porque parece proteção e não é.

**O token fica em `localStorage`.** É o lado ruim de um trade-off de dois lados. Em
`localStorage` qualquer JavaScript que rode na página lê o token, então um XSS vira
roubo de sessão. Um cookie `httpOnly` fecha essa porta, mas abre CSRF e passa a exigir
`SameSite`, endpoint de CSRF token e cuidado com o proxy do Vite, que faz o front e a
API parecerem a mesma origem em desenvolvimento e não em produção. Escolhi
`localStorage` porque a superfície de XSS aqui é pequena e controlada: o React escapa
por padrão e não há um único `dangerouslySetInnerHTML` no projeto. Em produção eu
inverteria: cookie `httpOnly` com `Secure` e `SameSite=Strict`, mais token de CSRF, e
aceitaria a complexidade.

**Não há rate limit no login.** O `password_verify` contra hash dummy protege contra
descobrir emails válidos por timing, mas nada impede alguém de tentar dez mil senhas
para `admin@toro.test`. O bcrypt segura a taxa por ser lento, e só. Faltou limite por
IP e por email, com bloqueio progressivo, de preferência na borda em vez de dentro do
PHP.

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
POST   /sales                       admin
POST   /sales/{external_id}/cancel  admin

GET    /me/wallet                   seller      saldo e extrato paginado do token
```

Erro sempre no mesmo formato, com `fields` presente só quando a falha é de validação:

```json
{"error":{"code":"validation_failed","message":"dados inválidos","fields":{"quantity":"deve estar entre 1 e 1000000"}}}
```

Seller batendo em rota de admin recebe 403, e o admin batendo em `/me/wallet` também,
porque a carteira é do vendedor. Sem token ou com token inválido é 401. O frontend
trata os dois: 401 limpa a sessão e volta para o login, 403 mostra "sem permissão".

O arquivo `requests.http` na raiz cobre todos os endpoints, incluindo os casos de 401,
403, 404, 422 e o reenvio de venda duplicada. Ele encadeia os tokens do login, então dá
para abrir no REST Client do VS Code ou no cliente HTTP do IntelliJ e ir de cima para
baixo.

## Testes

São 19 testes de integração que rodam contra o MySQL do compose, cobrindo o motor de
pontuação e a carteira: crédito e consumo de verba, rejeição por estouro, mesmo
`external_id` creditado uma vez só, estorno com devolução de verba, cancelamento
repetido sem débito novo, cancelamento de venda inexistente, verba devolvida sendo
reutilizável, campanha fora de vigência, produto inativo, estorno usando o ledger em
vez do valor atual do produto, e a carteira não enxergando entrada de outro vendedor.

Com o compose de pé:

```bash
docker compose exec api php vendor/bin/phpunit
```

Os testes criam e apagam os próprios dados no `tearDown`, então rodam contra o mesmo
banco do seed sem sujar.

## O que ficou de fora

Nenhum dos bônus de importação entrou: não há import de vendas por CSV, o admin lança
uma venda por vez pelo formulário. Paginação e filtro existem só na carteira, que é a
listagem que cresce sem limite; produtos e campanhas voltam a lista inteira. De
campanha só dá para criar e listar: não há edição, nem fechar campanha pela API, o que
significa que mudar `budget_total` depois de criada é `UPDATE` na mão.

Não existe endpoint para listar vendedores, então a tela de lançar venda pede o
`seller_id` digitado, e é por isso que os ids do seed estão documentados acima. Numa
próxima passada isso seria um `GET /sellers` e um select.

A auditoria ficou pela metade: toda venda grava `created_by_user_id`, então dá para
saber quem lançou o quê consultando o banco, mas não há tabela de log de eventos nem
tela que mostre isso.

O frontend roda em modo de desenvolvimento do Vite, com proxy `/api` apontando para o
nginx. Não há build de produção nem servidor estático no compose, e não há teste de
frontend.

## O que eu faria com mais tempo

Uma **fila para importação em massa** seria a primeira coisa. O CSV de vendas do mês
não cabe num request HTTP: lançar dez mil vendas em série segura a conexão por minutos
e, pior, cada lançamento pega o lock da campanha, então o import inteiro vira uma fila
implícita disputando a mesma linha. O desenho seria receber o arquivo, gravar as linhas
como pendentes e devolver 202 com um id de lote; um worker consumindo a fila lança uma
a uma, reaproveitando o `external_id` de cada linha como chave de idempotência, o que
deixa o lote inteiro seguro para reprocessar. No fim, um relatório do lote com o que
entrou e o que foi rejeitado por verba ou validação.

**Cache do saldo invalidado por evento.** Hoje todo `GET /me/wallet` faz um `SUM` sobre
o ledger do vendedor. Com o índice em `(seller_id, created_at)` isso aguenta bem o
volume do desafio, mas o custo cresce linearmente com o histórico e o extrato é a tela
que o vendedor mais abre. Eu guardaria o saldo materializado por vendedor, escrito
dentro da mesma transação que grava a entrada no ledger, com o ledger continuando como
fonte da verdade e um job de reconciliação comparando os dois. Cache invalidado por
evento de escrita, nunca por TTL: saldo errado por alguns segundos é pior que saldo
lento.

**Rate limit no login**, pelo motivo da seção de segurança. Contador por IP e por email
com janela deslizante, bloqueio progressivo, e de preferência no nginx, para a
tentativa nem chegar ao PHP e ao bcrypt.

**Rotação do segredo do JWT.** Hoje o segredo é um só e trocá-lo derruba todo mundo na
hora. Eu passaria a assinar com um `kid` no header e manter um conjunto de chaves
válidas para verificação, com a nova assinando e a antiga só verificando até expirar a
última hora de tokens emitidos. Isso torna a troca de segredo uma operação rotineira em
vez de um incidente, e é o que faz a revogação por vazamento de chave virar viável.

**Teste de carga na trava de verba.** Os testes atuais provam a regra, não a
concorrência: eles rodam em série e nunca disputam o `FOR UPDATE`. Eu quero uma bateria
que dispare centenas de lançamentos paralelos numa campanha cuja verba comporta só uma
fração deles, e depois verifique três invariantes: `budget_used` nunca passou de
`budget_total`, `budget_used` bate exatamente com `SUM(credit) - SUM(debit)` da
campanha, e a contagem de créditos é igual à de vendas aprovadas. O mesmo vale para o
`external_id` repetido em paralelo, que precisa terminar com um crédito só. É o tipo de
bug que não aparece em teste sequencial e aparece em produção.
