# ixc-provedor-client

A PHP client for the **IXC Provedor** (IXCSoft) webservice API. Composer package `marcosmarcolin/ixc-provedor-client`, namespace `Marcolin\IxcProvedor\`.

This file is the specification of how the package works. Behavior changes start here.

## Scope

**It is:**
- A thin, typed client for `https://HOST/webservice/v1`: the table routes (list, find, insert, update, delete) and the API resources ("Recursos da API"), which follow no common pattern.
- PHP >= 7.4. HTTP through PSR-18 (`psr/http-client`) and PSR-17 (`psr/http-factory`), discovered with `php-http/discovery` when not injected.

**It is not** (out of scope; don't implement without an explicit decision):
- Typed models or DTOs per table. Rows are associative arrays.
- A dependency on Guzzle, Symfony or any framework. Framework adapters, if ever, go in separate packages.
- Retries, caching or rate limiting. Those belong to the injected PSR-18 client or middleware.

## Sources of truth

When in doubt about behavior, these win, in this order:
1. Responses from a real IXC Provedor instance, captured as anonymized fixtures in `tests/Fixtures/`.
2. The official API wiki: https://wikiapiprovedor.ixcsoft.com.br (pages load through `formulario.php?form=<table>` and `recursos_api.php`).
3. The official `IXCsoft\WebserviceClient` class used by the wiki examples.

Fixtures are captured with `docker compose run --rm php composer capture` (`tools/capture-fixtures.php`) against the instance in `.env`. Its write scenarios create, change and delete a client, so it only runs against a disposable instance. Raw responses stay in the gitignored `tests/Fixtures/raw/`; only the anonymized ones are committed, after checking that no real value survived.

Never implement from memory. When unsure, check the wiki or capture a real response.

## Definition of done

A change is done only when all of these hold:
1. **It is tested.** New behavior comes with tests, and a bug fix comes with a test that fails without the fix. HTTP is faked with `php-http/mock-client`; tests never hit the network.
2. **No secrets:** gitleaks passes, locally (hooks) and in CI.
3. **The quality tools pass:** PHPUnit, phpcs (PSR-12) and PHPStan at level `max`, locally in Docker and in CI, on every PHP version from 7.4 to the latest.
4. **The docs are current:** both READMEs, CLAUDE.md and examples match the behavior.

## Code rules

- Follow good development practices: small focused classes, single responsibility, dependency injection, no global state, no hidden side effects, clear names.
- **PHP 7.4 compatible.** No union types, `mixed`, `match`, named arguments, constructor promotion, nullsafe operator, `static` return type, enums or `readonly`. CI runs on 7.4, so these fail there.
- **Everything is typed:**
  - `declare(strict_types=1);` in every file;
  - typed parameters, returns and properties;
  - immutable value objects (private properties, no setters, `with*()` methods returning a copy);
  - generics and array shapes in PHPDoc where PHP's types can't express them.
- **Everything is testable:** dependencies are injected, never constructed deep inside. Discovery happens only in the `Client::create()` factory.
- Classes are `final` by default.
- **Secrets never leak:** the token stays out of exception messages, `__debugInfo()`, logs, fixtures and git history. gitleaks (`.gitleaks.toml`, with a rule for the IXC token) runs in the `pre-commit` and `pre-push` hooks and in CI; never bypass it with `--no-verify`.
- PHPStan at level `max`. No new `ignoreErrors` without a written justification.
- Short comments, one line, only to explain why.
- No `echo`/`print`. Errors become exceptions.
- No production dependencies besides the PSR interfaces and `php-http/discovery`.
- Code, comments, CLAUDE.md, commits and PRs in English. `README.md` is in Portuguese, the language of the package's users, and `README.en.md` is its English translation; both start with a language switcher and change together.

## Verification

Never run PHP, Composer, PHPUnit, phpcs or PHPStan on the host. Always use Docker (PHP 7.4 by default; set `PHP_VERSION` to try another):

```sh
docker compose run --rm php composer install
docker compose run --rm php vendor/bin/phpunit
docker compose run --rm php composer lint
PHP_VERSION=8.4 docker compose build php && PHP_VERSION=8.4 docker compose run --rm php vendor/bin/phpunit
git config core.hooksPath .githooks   # once per clone: gitleaks before every commit and push
```

## Workflow

- **Every commit follows [Conventional Commits](https://www.conventionalcommits.org/):** `feat:`, `fix:`, `docs:`, `test:`, `refactor:`, `chore:`, `ci:`, and `!` or a `BREAKING CHANGE:` footer for breaking changes.
- Commits are signed (GPG) and authored as `marcolindev@gmail.com`; this is configured in the repo's local git config.
- **After the first commit, every change goes through a pull request.** No direct pushes to `main`. One focused change per PR, with green CI, tests and a description of what changed and how it was verified.
- **Merges, tags, releases and anything published need Marcos's explicit approval each time.** A green CI is not approval.
- The repo is `marcosmarcolin/ixc-provedor-client`, private until the first usable release.

## Architecture (target)

```
src/
  Client.php              entry point: create(), table(), resource()
  ApiVersion.php          webservice API version (v1), the "webservice/v1" path prefix
  Credentials.php         "<user id>:<secret>" token, Basic authorization header
  Transport.php           builds PSR-7 requests, sends them, maps HTTP failures to exceptions
  Table.php               list, find, create, update, delete, each (all pages)
  Resource.php            list (ixcsoft: listar) or post with free-form parameters
  Query.php               qtype/query/oper/page/rp/sortname/sortorder plus grid_param filters
  Page.php                one page of a listing: page, total, rows
  Response.php            raw response of a resource: status, body, json()
  Exception/              IxcException (interface), TransportException, AuthenticationException, ApiErrorException
```

## API behavior

What the fixtures in `tests/Fixtures/` show (captured from IXC Provedor on PHP 7.4), plus the wiki and the official client. Items marked *to confirm* still need a capture.

- **Base URL:** `https://HOST/webservice/v1/<table or resource>`. Self-signed certificates are common; that is configured on the injected PSR-18 client, not here.
- **API version:** the `v1` in the path is an `ApiVersion`, never a hardcoded string. The server accepts only `v1`; any other answers `{"type":"error","message":"Versão v2 do webservice não está disponível!"}` with HTTP 200 (`error_unknown_version`). `v1` is the only supported version and the default (`ApiVersion::latest()`), and `fromString()` rejects anything else. Supporting a new version means capturing its responses, adding it to `ApiVersion::SUPPORTED`, and branching only where its behavior differs.
- **Authentication:** `Authorization: Basic base64("<user id>:<secret>")`; the secret is 64 hex characters. A wrong secret or unknown user is rejected by the web server with HTTP 401 and an HTML page, not JSON (`error_wrong_secret`, `error_unknown_user`).
- **Errors are mostly HTTP 200.** Unknown table or resource, unknown version, a client IP outside the networks allowed for the user's group, and every validation failure answer 200 with `{"type":"error","message":...}`. So a response is an error when its JSON has `type` `error`, whatever the status. A rejected token is HTTP 401, and any other status of 400 or more is an error too; an unexpected failure can answer 200 with an HTML `<div>` (`list_invalid_field`).
- **Messages** may hold HTML line breaks (`<br />`) between several validation errors. *To confirm*: one capture (IP not allowed) came double-encoded (`n\u00c3\u00a3o`), the others are plain UTF-8 escapes; check on an instance whose files are still ISO-8859-1.
- **Content type** is not reliable: listings and writes answer `text/x-json; charset=utf-8`, the routing errors `text/html; charset=ISO-8859-1`. The header `ixc-type-response: application/json` makes listings answer `application/json`. Decide by the body, never by the content type.
- **Table routes:**
  - list: `POST /<table>` with header `ixcsoft: listar`; the body (JSON or form data, both accepted) has `qtype`, `query`, `oper`, `page`, `rp`, `sortname`, `sortorder`, and optionally `grid_param`, a JSON-encoded string of `[{"TB": field, "OP": operator, "P": value}]`. The routing is by the header, so `GET` with it works too. The answer is `{"page": "1", "total": ..., "registros": [...]}`; `total` is sometimes an int and sometimes a numeric string (`list_page`, `list_cidade`), and an empty listing has no `registros` key: `{"page":"1","total":0}` (`list_empty`). Every value in a row is a string.
  - find: a listing with `qtype` `<table>.id`, `oper` `=`.
  - insert: `POST /<table>` with the row as JSON. Success is `{"type":"success","message":"Registro inserido com sucesso!","id":"2","atualiza_campos":[...]}`, with `id` as a string. The server validates the whole form (for `cliente`, more required fields than the wiki marks), so a row is built by reading an existing one or the wiki's full template and changing only what is needed. Dates are sent as `d/m/Y`, while listings return `Y-m-d`.
  - update: `PUT /<table>/<id>` with the full row as JSON; a partial row fails validation. Success is `{"type":"success","message":"Registro atualizado com sucesso!","id":"2",...}`. An id that does not exist also answers success and changes nothing (`update_not_found`).
  - patch: `PATCH /<table>/<id>` exists but validates like `PUT`, so it needs the full row too (`patch`, `patch_partial`).
  - delete: `DELETE /<table>/<id>`; success is `{"type":"success","message":"Registro 2 excluido com sucesso!"}`, also when the id does not exist (`delete_not_found`).
- **Resources:** names are free-form strings, some with a numeric suffix (`cliente_contrato_15464`, `radusuarios_25452`), so they are never a closed list. *To confirm with captures*:
  - called like a listing (`ixcsoft: listar`) with their own parameters: `get_boleto`, `desbloqueio_confianca`, `desconectar_clientes`, `cliente_contrato_ativar_cliente`, `gerar_protocolo_atendimento`, `liberacao_reducao_contrato_29157`...
  - called with a plain `POST`: `su_oss_chamado_fechar`, `su_oss_chamado_analisar`, `su_oss_chamado_alterar_setor`, `fn_areceber_recebimentos_baixas_novo`, `su_transf_atendimento_26466`...
  - the answer format varies (JSON, a bare number, base64 content), so a resource returns a raw `Response`.
- **Not copied from the official client:** it runs every decoded value through `utf8_decode()`, turning UTF-8 into ISO-8859-1; this client returns UTF-8 as decoded.

## Roadmap

1. [x] Scaffold: Composer, Docker, PHPUnit, PHPStan max, phpcs, CI, `Credentials`.
2. [ ] Capture real responses from an instance as anonymized fixtures: tables done (`cliente`), resources pending.
3. [ ] `Transport` and exceptions.
4. [ ] `Query`, `Page` and `Table` (list, find, create, update, delete, each).
5. [ ] `Resource` and `Response`.
6. [ ] `Client::create()` with discovery.
7. [ ] Complete both READMEs (pt-BR and en) and examples.
8. [ ] First release (`v0.1.0`).
