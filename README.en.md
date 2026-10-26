# ixc-provedor-client

[Português](README.md) | **English**

[![CI](https://github.com/marcosmarcolin/ixc-provedor-client/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/marcosmarcolin/ixc-provedor-client/actions/workflows/ci.yml)
[![Packagist Version](https://img.shields.io/packagist/v/marcosmarcolin/ixc-provedor-client)](https://packagist.org/packages/marcosmarcolin/ixc-provedor-client)
[![PHP Version](https://img.shields.io/packagist/dependency-v/marcosmarcolin/ixc-provedor-client/php)](https://packagist.org/packages/marcosmarcolin/ixc-provedor-client)
[![Downloads](https://img.shields.io/packagist/dt/marcosmarcolin/ixc-provedor-client)](https://packagist.org/packages/marcosmarcolin/ixc-provedor-client/stats)
[![License](https://img.shields.io/packagist/l/marcosmarcolin/ixc-provedor-client)](LICENSE)

A typed PHP client for the **IXC Provedor** API (webservice), by IXCSoft: list, create and change records and call the system's actions without tripping over the API's quirks.

```php
$ixc = Client::create('https://ixc.yourisp.com.br', '6:YOUR_TOKEN');

foreach ($ixc->route('cliente')->each(Query::where('ativo', '=', 'S')) as $client) {
    echo $client['razao'], PHP_EOL;
}

$ixc->route('cliente')->update('42', ['obs' => 'Changed by the API']);
$ixc->resource(Resources::DESBLOQUEIO_CONFIANCA)->list(['id' => '42']);
```

> Built on the public IXC Provedor API, documented at [wikiapiprovedor.ixcsoft.com.br](https://wikiapiprovedor.ixcsoft.com.br).

## Why use it

- **Routes and resources**, in IXC's own model: listings with filters, automatic paging with `each()`, find, insert, update and delete (several ids in one request), and constants for every resource the wiki documents.
- **Dates that don't get lost:** listings return `Y-m-d`, writes expect `d/m/Y` and, unconverted, silently save them blank or wrong. The package converts them for you.
- **Safe partial updates:** `update()` reads the record, applies only what changed and writes the whole record back, as the API requires. For a missing id it throws, instead of the API's false success.
- **Errors become exceptions:** the API answers errors with HTTP 200; here they become `ApiErrorException`, with the Portuguese message repaired and ready to show to users.
- **File uploads** as multipart, the only format the API accepts.
- **Light and compatible:** PHP 7.4 to 8.x, a built-in HTTP client on curl or any PSR-18 client (Guzzle, Symfony), no framework.
- **Tested against a real instance:** the documented behavior comes from responses captured from IXC Provedor, kept as anonymized fixtures.

## Requirements

- PHP 7.4 or later, with the `curl` extension
- A PSR-17 factory implementation, such as `nyholm/psr7` or `guzzlehttp/psr7`

## Installation

```sh
composer require marcosmarcolin/ixc-provedor-client nyholm/psr7
```

## Getting started

Generate the token in **Configurações > Usuários**, on a user with **Permite acesso ao webservice** checked, and pass the instance's address, without `/webservice/v1`:

```php
use Marcolin\IxcProvedor\Client;
use Marcolin\IxcProvedor\Exception\ApiErrorException;
use Marcolin\IxcProvedor\Query;
use Marcolin\IxcProvedor\Resources;

$ixc = Client::create('https://ixc.yourisp.com.br', '6:YOUR_TOKEN');

// Routes: the system's forms
$clients = $ixc->route('cliente');
$page = $clients->list(Query::where('razao', 'L', 'Silva')->withPerPage(50));
$client = $clients->find('42');   // array or null

// Resources: the system's actions
$protocol = $ixc->resource(Resources::GERAR_PROTOCOLO_ATENDIMENTO)->list()->json();

// Errors
try {
    $clients->update('42', ['obs' => 'Changed by the API']);
} catch (ApiErrorException $e) {
    $e->getMessage();   // "Preencha o campo Telefone Celular; CPF/CNPJ obrigatório!"
}
```

## Documentation

| Guide | Covers |
|---|---|
| [Configuration](docs/configuration.en.md) | the token, self-signed certificates, another HTTP client (Guzzle, Symfony) and the API version |
| [Routes](docs/routes.en.md) | listing, filtering and paging; find, insert, update and delete; files, dates and false successes |
| [Resources](docs/resources.en.md) | calling the wiki's actions, reading the answer, and the formats each resource returns |
| [Errors](docs/errors.en.md) | the exceptions and when each one is thrown, and the API's messages |
| [Development](docs/development.en.md) | tests, linters, secret scanning and fixture capture, all in Docker |

See also the [CHANGELOG](CHANGELOG.md), the [contributing guide](CONTRIBUTING.md) (in Portuguese) and the [security policy](SECURITY.md#security-policy-english): report vulnerabilities privately.

## License

MIT
