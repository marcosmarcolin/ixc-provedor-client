# Changelog

Todas as mudanças relevantes deste pacote ficam registradas aqui. O formato segue o [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/), e as versões seguem o [Versionamento Semântico](https://semver.org/lang/pt-BR/).

## [Unreleased]

### Alterado

- O README ficou mais curto, com um exemplo de abertura e uma lista de motivos para usar o pacote; os detalhes foram para guias em `docs/` (configuração, rotas, recursos, erros, desenvolvimento).
- A documentação para usuários (README, guias, política de segurança e este changelog) e a descrição do pacote no Composer são só em português; as traduções para o inglês foram removidas.

## [0.2.0] - 2026-10-25

### Adicionado

- `Response::message()` e `messages()`: o `message` da resposta de um recurso como texto simples, como `"Contrato ativado com sucesso!"`, separado em `<br />` como o de `ApiErrorException`; vazio quando a resposta não tem mensagem.
- `Route::delete()` aceita vários ids, `delete('18', '19')`, e exclui todos numa requisição só, já que a API os aceita separados por vírgula.

### Corrigido

- As mensagens que a API manda com codificação dupla, como as recusas de acesso por IP ou horário (`Seu IP nÃ£o está liberado...`), chegam corrigidas em `ApiErrorException::getMessage()` e `messages()`; `apiMessage()` continua como foi enviada.
- Um erro de PATCH respondido como `{"tipo":"error","valor":...}`, como fazem `cliente` e `cliente_contrato` para um id que não existe, agora lança `ApiErrorException` com a mensagem da API, em vez de `UnexpectedResponseException`.

## [0.1.0] - 2026-10-25

Primeira versão: um cliente para a API pública do IXC Provedor (`/webservice/v1`), cobrindo as rotas e os recursos que a wiki documenta.

### Adicionado

- `Client::create($host, $token, $selfSigned = false)` sobre um cliente HTTP próprio (ext-curl): como no cliente oficial, o certificado é verificado, a não ser que `$selfSigned` seja `true`.
- `Client::withHttpClient($host, $token, $httpClient)` para qualquer cliente PSR-18, como Guzzle ou Symfony HttpClient.
- Rotas (os formulários do sistema) por `Client::route($name)`:
  - `list()` com `Query` (condição, filtros `grid_param`, paginação e ordenação) e `each()` sobre todas as páginas;
  - `find()`, `create()` (também com arquivos, como multipart form data, por `Attachment`), `update()`, `replace()`, `patch()` e `delete()`;
  - `update()` lê o registro, aplica as alterações e grava o registro inteiro de volta, lançando `NotFoundException` para um id que não existe;
  - as gravações mandam datas `Y-m-d` como `d/m/Y`, já que a API salva uma data `Y-m-d` em branco ou corrompida enquanto responde sucesso.
- Recursos (as ações do sistema) por `Client::resource($name)`, com `list()` e `post()` como a wiki os chama e a `Response` bruta (`status()`, `header()`, `body()`, `json()`); `Resources` nomeia os 17 recursos que a wiki documenta.
- Exceções sob `IxcException`: `ApiErrorException` (o `{"type":"error"}` da API, em geral com HTTP 200, com a mensagem da própria API em português), `AuthenticationException`, `TransportException`, `NotFoundException` e `UnexpectedResponseException`.
- `ApiVersion`, com `v1` como única versão suportada.
- PHP 7.4 a 8.5.

[Unreleased]: https://github.com/marcosmarcolin/ixc-provedor-client/compare/v0.2.0...HEAD
[0.2.0]: https://github.com/marcosmarcolin/ixc-provedor-client/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/marcosmarcolin/ixc-provedor-client/releases/tag/v0.1.0
