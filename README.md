# ixc-provedor-client

**Português** | [English](README.en.md)

Cliente PHP para a API (webservice) do **IXC Provedor**, da IXCSoft.

> **Em desenvolvimento.** A API pública ainda não está pronta; veja o roadmap no [CLAUDE.md](CLAUDE.md).

## Requisitos

- PHP 7.4 ou superior
- Um cliente HTTP PSR-18 e uma factory PSR-17 (por exemplo, `guzzlehttp/guzzle` ou `symfony/http-client` com `nyholm/psr7`)

## Autenticação

O token é gerado em **Configurações > Usuários**, num usuário com **Permite acesso ao webservice** marcado. Ele tem o formato `<id do usuário>:<segredo>`:

```php
use Marcolin\IxcProvedor\Credentials;

$credentials = Credentials::fromToken('6:4dacdb8e47193e8cbbabe508c3c59b45...');
```

## Versão da API

As rotas ficam em `https://HOST/webservice/v1`. A versão é um `ApiVersion`; hoje só existe a `v1`, que é o padrão:

```php
use Marcolin\IxcProvedor\ApiVersion;

$version = ApiVersion::v1();   // ou ApiVersion::fromString('v1')
$version->basePath();          // "webservice/v1"
```

## Desenvolvimento

Tudo roda em Docker (PHP 7.4 por padrão):

```sh
docker compose run --rm php composer install
docker compose run --rm php vendor/bin/phpunit
docker compose run --rm php composer lint
```

Os hooks de `pre-commit` e `pre-push` rodam o [gitleaks](https://github.com/gitleaks/gitleaks) (também via Docker) e bloqueiam commits e pushes que contenham segredos, como um token do IXC. Ative-os uma vez por clone:

```sh
git config core.hooksPath .githooks
```

O CI roda o mesmo scan em todo o histórico.

## Licença

MIT
