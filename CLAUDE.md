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

**Only a consumer of the public API.** Nothing from IXC Provedor's own source code goes into this repository, its docs, tests, commits or pull requests: no file, function or internal names, no messages or behavior known only from reading it. Behavior is documented only as observed through the API (captures), the public wiki or the official client.

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
- **Error messages:** `ApiErrorException::getMessage()` is the API's own text (Portuguese, `<br />` split and joined with `; `), so applications can show it to users as is; the status is in `statusCode()`, the raw text in `apiMessage()`. Messages written by this package (invalid arguments, transport and authentication failures) are in English and never echo the host or the token. No configurable language: it would need global state or reach only part of the messages.
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
  Table.php               list, each (all pages), find, create, update (read, merge, write), replace, delete
  RowEncoder.php          write body: values as text, Y-m-d dates as d/m/Y
  Resource.php            list (ixcsoft: listar) or post with free-form parameters
  Query.php               qtype/query/oper/page/rp/sortname/sortorder plus grid_param filters
  Page.php                one page of a listing: page, total, rows
  Response.php            raw response of a resource: status, body, json()
  Exception/              IxcException (interface), TransportException, AuthenticationException, ApiErrorException,
                          NotFoundException, UnexpectedResponseException
```

## API behavior

What the fixtures in `tests/Fixtures/` show (captured from a real IXC Provedor), plus the public wiki and the official client. Items marked *to confirm* still need a capture.

- **Base URL:** `https://HOST/webservice/v1/<table or resource>`. Self-signed certificates are common; that is configured on the injected PSR-18 client, not here.
- **API version:** the `v1` in the path is an `ApiVersion`, never a hardcoded string. Another version answers `{"type":"error","message":"Versão v2 do webservice não está disponível!"}` with HTTP 200 (`error_unknown_version`). `v1` is the only supported version and the default (`ApiVersion::latest()`), and `fromString()` rejects anything else. Supporting a new version means capturing its responses, adding it to `ApiVersion::SUPPORTED`, and branching only where its behavior differs.
- **Authentication:** `Authorization: Basic base64("<user id>:<secret>")`; the secret is 64 hex characters. A wrong secret or unknown user answers HTTP 401 with an HTML page, not JSON (`error_wrong_secret`, `error_unknown_user`). A client IP outside the networks allowed for the user's group answers 200 with `{"type":"error"}`.
- **Errors are mostly HTTP 200** with `{"type":"error","message":...}`: unknown table or resource, unknown version, validation failures, a blocked IP, and resource errors. So a response is an error when its JSON has `type` `error`, whatever the status; any other status of 400 or more is an error too. An unexpected failure can answer 200 with an HTML `<div>` (`list_invalid_field`).
- **False successes:** some operations answer `{"type":"success"}` for an id that does not exist: `PUT` and `DELETE` on a table (`update_not_found`, `delete_not_found`) and the resource `cliente_contrato_15464` (`resource_contract_status_missing`). Never read success as "the record existed".
- **Messages** may hold HTML line breaks (`<br />`) between several validation errors. *To confirm*: one capture (blocked IP) came double-encoded (`n\u00c3\u00a3o`), the others are plain UTF-8 escapes.
- **Content type** is not reliable: `text/x-json; charset=utf-8` on listings and writes, `text/html; charset=ISO-8859-1` on some errors and on resources. The header `ixc-type-response: application/json` makes listings answer `application/json`. Decide by the body, never by the content type.
- **Table routes:**
  - list: `POST /<table>` with header `ixcsoft: listar`; the body (JSON or form data, both accepted) has `qtype`, `query`, `oper`, `page`, `rp`, `sortname`, `sortorder`, and optionally `grid_param`, a JSON-encoded string of `[{"TB": field, "OP": operator, "P": value}]`. `GET` with the header answers the same (`list_get_method`). The answer is `{"page": "1", "total": ..., "registros": [...]}`; `total` is sometimes an int and sometimes a numeric string (`list_page`, `list_cidade`), and an empty listing has no `registros` key: `{"page":"1","total":0}` (`list_empty`). Every value in a row is a string.
  - operators seen working: `=`, `>`, and `L` (contains: `nome` `L` `Chapec` found the matching cities); the wiki also uses `!=`.
  - find: a listing with `qtype` `<table>.id`, `oper` `=`.
  - insert: `POST /<table>` with the row as JSON. Success is `{"type":"success","message":"Registro inserido com sucesso!","id":"2","atualiza_campos":[...]}`, with `id` as a string. The whole form is validated (for `cliente`, more required fields than the wiki marks), so a row is built by reading an existing one or the wiki's full template and changing only what is needed.
  - **dates:** writes take `d/m/Y`, listings return `Y-m-d`. A `Y-m-d` date in a write is answered with success and saved blank or corrupted: `1990-01-01` became `0001-01-00` (`update_as_listed`, `find_after_update_as_listed`). With `Y-m-d` → `d/m/Y` and `0000-00-00` → `""`, every date of a `cliente` row survives a read-and-write. So `RowEncoder` converts every write. Confirmed on `cliente`; other tables *to confirm*.
  - update: `PUT /<table>/<id>` with the full row as JSON; a partial row fails validation. Success is `{"type":"success","message":"Registro atualizado com sucesso!","id":"2",...}`, also for an id that does not exist. So `Table::update()` reads the record first (`NotFoundException` when missing), merges the changes and writes the whole row back; `Table::replace()` is the raw `PUT`.
  - patch: `PATCH /<table>/<id>` validates like `PUT`, so it needs the full row too (`patch`, `patch_partial`).
  - delete: `DELETE /<table>/<id>`; success is `{"type":"success","message":"Registro 2 excluido com sucesso!"}`, also when the id does not exist.
- **Resources:** names are free-form strings, some with a numeric suffix (`cliente_contrato_15464`, `radusuarios_25452`), so they are never a closed list; the wiki documents each one's parameters (`recursos_api.php`).
  - The wiki calls them in two ways: `$api->get()`, which is `POST` with `ixcsoft: listar` (`get_boleto`, `desbloqueio_confianca`, `gerar_protocolo_atendimento`, `cliente_contrato_15464`...), and `$api->post()`, a plain `POST` (`su_oss_chamado_fechar`, `su_oss_chamado_analisar`, `radusuarios_25452`...). `Resource::list()` and `Resource::post()` mirror them. Parameters go as given (the wiki already writes dates as `d/m/Y`); a JSON body works like the official client's form data (`resource_protocol_listar`, `resource_protocol_form`).
  - Answers seen: a JSON string (`gerar_protocolo_atendimento` → `"20261024162331914000"`, 20 digits, the same with or without `ixcsoft: listar`), a JSON object with `type` (`resource_contract_status_missing`), `{"type":"error"}` (`resource_trust_unlock_missing`, `resource_close_order_missing`), and an empty body with 200 (`get_boleto` with a missing invoice, `resource_boleto_missing`). The wiki also shows base64 content (`get_boleto` with `base64` `S`) and binary content (`base64` `N`), *to confirm*. So a resource returns a raw `Response`.
- **Not copied from the official client:** it runs every decoded value through `utf8_decode()`, turning UTF-8 into ISO-8859-1; this client returns UTF-8 as decoded.

## Roadmap

1. [x] Scaffold: Composer, Docker, PHPUnit, PHPStan max, phpcs, CI, `Credentials`.
2. [x] Capture real responses from an instance as anonymized fixtures (`cliente` and representative resources).
3. [x] `Transport` and exceptions.
4. [x] `Query`, `Page` and `Table` (list, find, create, update, delete, each).
5. [x] `Resource` and `Response`.
6. [x] `Client::create()` with discovery.
7. [ ] Complete both READMEs (pt-BR and en) and examples.
8. [ ] First release (`v0.1.0`).
