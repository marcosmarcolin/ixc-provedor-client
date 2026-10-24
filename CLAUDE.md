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

Never implement from memory. When unsure, check the wiki or capture a real response.

## Definition of done

A change is done only when all of these hold:
1. **It is tested.** New behavior comes with tests, and a bug fix comes with a test that fails without the fix. HTTP is faked with `php-http/mock-client`; tests never hit the network.
2. **No secrets:** gitleaks passes, locally (hooks) and in CI.
3. **The quality tools pass:** PHPUnit, phpcs (PSR-12) and PHPStan at level `max`, locally in Docker and in CI, on every PHP version from 7.4 to the latest.
4. **The docs are current:** README, CLAUDE.md and examples match the behavior.

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
- Code, comments, CLAUDE.md, commits and PRs in English. The README is in Portuguese, the language of the package's users.

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

What the wiki and the official client show. Items marked *to confirm* need a capture from a real instance before they are implemented.

- **Base URL:** `https://HOST/webservice/v1/<table or resource>`. Self-signed certificates are common; that is configured on the injected PSR-18 client, not here.
- **API version:** the `v1` in the path is an `ApiVersion`, never a hardcoded string. `v1` is the only supported version and the default (`ApiVersion::latest()`). `fromString()` rejects anything else, because routes and payloads are only known for v1. Supporting a new version means capturing its responses, adding it to `ApiVersion::SUPPORTED`, and branching only where its behavior differs.
- **Authentication:** `Authorization: Basic base64("<user id>:<secret>")`. A wrong or deleted token answers HTTP 401.
- **Table routes:**
  - list: `POST /<table>` with header `ixcsoft: listar` and a JSON body `qtype`, `query`, `oper`, `page`, `rp`, `sortname`, `sortorder`, and optionally `grid_param`, a JSON-encoded string of `[{"TB": field, "OP": operator, "P": value}]` (`OP` `L` is like). The answer is `{"page", "total", "registros"}`; *to confirm*: the shape of an empty listing.
  - insert: `POST /<table>` with the row as JSON. *To confirm*: the success and error bodies (expected `{"type":"success"|"error","message":...,"id":...}`).
  - update: `PUT /<table>/<id>` with the row as JSON.
  - delete: `DELETE /<table>/<id>`.
- **Resources:** names are free-form strings, some with a numeric suffix (`cliente_contrato_15464`, `radusuarios_25452`), so they are never a closed list.
  - called like a listing (`ixcsoft: listar`) with their own parameters: `get_boleto`, `desbloqueio_confianca`, `desconectar_clientes`, `cliente_contrato_ativar_cliente`, `gerar_protocolo_atendimento`, `liberacao_reducao_contrato_29157`...
  - called with a plain `POST`: `su_oss_chamado_fechar`, `su_oss_chamado_analisar`, `su_oss_chamado_alterar_setor`, `fn_areceber_recebimentos_baixas_novo`, `su_transf_atendimento_26466`...
  - the answer format varies (JSON, a bare number, base64 content), so a resource returns a raw `Response`.

## Roadmap

1. [x] Scaffold: Composer, Docker, PHPUnit, PHPStan max, phpcs, CI, `Credentials`.
2. [ ] Capture real responses from an instance (success, error, empty listing, resources) as anonymized fixtures.
3. [ ] `Transport` and exceptions.
4. [ ] `Query`, `Page` and `Table` (list, find, create, update, delete, each).
5. [ ] `Resource` and `Response`.
6. [ ] `Client::create()` with discovery.
7. [ ] Complete README and examples.
8. [ ] First release (`v0.1.0`).
