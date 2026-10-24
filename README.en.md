# ixc-provedor-client

[Português](README.md) | **English**

PHP client for the **IXC Provedor** API (webservice), by IXCSoft.

> **Under development.** The public API is not ready yet; see the roadmap in [CLAUDE.md](CLAUDE.md).

## Requirements

- PHP 7.4 or later
- A PSR-18 HTTP client and a PSR-17 factory (for example, `guzzlehttp/guzzle`, or `symfony/http-client` with `nyholm/psr7`)

## Authentication

The token is generated in **Configurações > Usuários**, on a user with **Permite acesso ao webservice** checked. It has the form `<user id>:<secret>`:

```php
use Marcolin\IxcProvedor\Credentials;

$credentials = Credentials::fromToken('6:4dacdb8e47193e8cbbabe508c3c59b45...');
```

## API version

The routes live under `https://HOST/webservice/v1`. The version is an `ApiVersion`; today `v1` is the only one, and it is the default:

```php
use Marcolin\IxcProvedor\ApiVersion;

$version = ApiVersion::v1();   // or ApiVersion::fromString('v1')
$version->basePath();          // "webservice/v1"
```

## Errors

Every exception from a request implements `Marcolin\IxcProvedor\Exception\IxcException`:

| Exception | When |
|---|---|
| `ApiErrorException` | the API answered `{"type":"error"}`, usually with HTTP 200, or an HTTP error status |
| `AuthenticationException` | HTTP 401: the token was rejected (wrong secret or unknown user) |
| `TransportException` | the request got no answer (DNS, connection, TLS, timeout) |

An `ApiErrorException`'s message is the API's own text, in Portuguese, so it can be shown to users:

```php
try {
    // ...
} catch (ApiErrorException $e) {
    $e->getMessage();   // "Preencha o campo Telefone Celular; CPF/CNPJ obrigatório!"
    $e->messages();     // ["Preencha o campo Telefone Celular", "CPF/CNPJ obrigatório!"]
    $e->statusCode();   // 200
}
```

## Development

Everything runs in Docker (PHP 7.4 by default):

```sh
docker compose run --rm php composer install
docker compose run --rm php vendor/bin/phpunit
docker compose run --rm php composer lint
```

The `pre-commit` and `pre-push` hooks run [gitleaks](https://github.com/gitleaks/gitleaks) (also through Docker) and block commits and pushes that contain secrets, such as an IXC token. Enable them once per clone:

```sh
git config core.hooksPath .githooks
```

CI runs the same scan over the whole history.

The fixtures in `tests/Fixtures/` are real, anonymized responses, captured with `docker compose run --rm php composer capture` against the instance set in `.env` (`IXC_HOST`, `IXC_TOKEN`, `IXC_INSECURE`). The capture creates, changes and deletes a client: use a disposable instance only.

## License

MIT
