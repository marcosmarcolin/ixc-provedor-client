# ixc-provedor-client

[Português](README.md) | **English**

> Built on the public IXC Provedor API, documented at <a href="https://wikiapiprovedor.ixcsoft.com.br" target="_blank" rel="noopener noreferrer">wikiapiprovedor.ixcsoft.com.br</a>.

PHP client for the **IXC Provedor** API (webservice), by IXCSoft.

## Requirements

- PHP 7.4 or later, with the `curl` extension
- A PSR-17 factory implementation, such as `nyholm/psr7` or `guzzlehttp/psr7`

## Installation

```sh
composer require marcosmarcolin/ixc-provedor-client nyholm/psr7
```

## Getting started

The token is generated in **Configurações > Usuários**, on a user with **Permite acesso ao webservice** checked, and has the form `<user id>:<secret>`. The user's group defines the networks it may connect from: from anywhere else, the API answers an error.

```php
use Marcolin\IxcProvedor\Client;

$ixc = Client::create('https://ixc.yourisp.com.br', '6:YOUR_TOKEN');
```

Pass only the instance's address, without `/webservice/v1`: the wiki examples include that path, but here it is rejected.

### Certificate

The instance's certificate is verified. To accept a self-signed certificate, pass `true` as the third parameter, as the official client's `$selfSigned`:

```php
$ixc = Client::create('https://192.168.0.10', '6:YOUR_TOKEN', true);
```

### Another HTTP client

The package has its own HTTP client, on curl. To use another PSR-18 client, such as Guzzle or Symfony HttpClient, pass it to `withHttpClient()`; its own settings then decide the certificate, timeouts and proxy:

```php
$ixc = Client::withHttpClient('https://ixc.yourisp.com.br', '6:YOUR_TOKEN', new \GuzzleHttp\Client(['verify' => false]));
```

## Routes

In IXC's model, a **route** is one of the system's forms (`cliente`, `cliente_contrato`, `fn_areceber`...), with listing and records: the API validates a request as the form validates its screen. Actions such as activating a contract or generating a bank slip are **resources** (next section).

```php
use Marcolin\IxcProvedor\Query;

$clients = $ixc->route('cliente');

// One page
$page = $clients->list(
    Query::where('razao', 'L', 'Silva')     // L: contains
        ->andWhere('ativo', '=', 'S')       // extra conditions (grid_param)
        ->orderBy('id', 'desc')
        ->withPerPage(50)
);
$page->total();   // records matching the query, across all pages
$page->rows();    // the rows of this page

// Every page
foreach ($clients->each(Query::where('ativo', '=', 'S')) as $client) {
    // ...
}

$client = $clients->find('42');                           // array or null
$id = $clients->create($row);                             // id of the new record
$clients->update('42', ['obs' => 'Changed by the API']);  // changes only these fields
$clients->delete('42');
```

Files go in the form's file field, in a `create()`; the package sends the row as multipart, the only format in which the API takes files:

```php
use Marcolin\IxcProvedor\Attachment;

$ixc->route('cliente_arquivos')->create(
    ['id_cliente' => '42', 'descricao' => 'Signed contract'],
    ['local_arquivo' => Attachment::fromPath('/tmp/contrato.pdf', 'application/pdf')]
);
```

- Fields may be given without the route name: `razao` becomes `cliente.razao`. Operators are the API's, such as `=`, `!=`, `>` and `L` (contains).
- Every value comes back as a string, ids included.
- **Insert:** the API validates the whole form. Build the row from an existing record (`find`) or the wiki's full template and change only what is needed.
- **Dates:** listings return `Y-m-d`, but writes expect `d/m/Y`. A `Y-m-d` date is saved blank or wrong, and the API still answers success. So the package converts `Y-m-d` to `d/m/Y` on every write; dates can be sent as the listing returned them.
- **Update:** `update()` reads the record, applies the changes and writes the whole record back, because the API validates every field (two requests). For a missing id it throws `NotFoundException`. To write a full row directly, use `replace($id, $row)`; `patch($id, $row)` does the same with the `PATCH` method, which the API also validates as the whole record.
- **False successes:** the API answers success to `replace()` and `delete()` of an id that does not exist.

## Resources

Resources ("Recursos da API" in the [wiki](https://wikiapiprovedor.ixcsoft.com.br)) are the system's actions, called by name with the parameters the wiki documents for each. `list()` matches the wiki examples' `$api->get()`, and `post()` their `$api->post()`.

The resources the wiki documents have constants in `Resources`, named after the wiki's titles; any other name can be given as a string:

```php
use Marcolin\IxcProvedor\Resources;

$protocol = $ixc->resource(Resources::GERAR_PROTOCOLO_ATENDIMENTO)->list()->json();   // "20261024162331914000"

$response = $ixc->resource(Resources::DESBLOQUEIO_CONFIANCA)->list(['id' => '42']);

$response = $ixc->resource('other_resource_12345')->post([...]);   // a name outside the list

$response = $ixc->resource(Resources::FINALIZAR_ORDEM_SERVICO)->post([
    'id_chamado' => '101',
    'data_inicio' => '02/12/2019 10:30:00',
    'data_final' => '04/12/2019 14:16:00',
    // ...
]);

$response->status();
$response->body();   // the raw answer: JSON, text, base64 or empty
$response->json();   // decoded; throws UnexpectedResponseException when it is not JSON
```

Business-rule errors (`{"type":"error"}`, such as "contract already active") become `ApiErrorException`. Otherwise, each resource answers in its own format, and some answer success even for a missing id. Check each one's answer. Some answers seen:

- `gerar_protocolo_atendimento`: the protocol as a JSON string;
- `get_boleto`: the data as JSON (`tipo_boleto` `dados`), the PDF in base64 (`arquivo` with `base64` `S`) or the raw PDF (`base64` `N`); an empty body for a missing invoice;
- `desconectar_clientes`: the outcome comes inside `msg[]` (`{"msg":[{"type":"error",...}]}`), with no top-level `type`, so it never becomes an exception;
- `imprimir_nota`: an HTML page when the invoice does not exist.

## API version

The routes live under `https://HOST/webservice/v1`. Today `v1` is the only version and the default; it can be passed explicitly:

```php
use Marcolin\IxcProvedor\ApiVersion;

$ixc = Client::create('https://ixc.yourisp.com.br', '6:YOUR_TOKEN', false, ApiVersion::v1());
```

## Errors

Every exception from a request implements `Marcolin\IxcProvedor\Exception\IxcException`:

| Exception | When |
|---|---|
| `ApiErrorException` | the API answered `{"type":"error"}`, usually with HTTP 200, or an HTTP error status |
| `NotFoundException` | `update()` of a record that does not exist |
| `UnexpectedResponseException` | the answer is not what the route returns, such as an HTML failure page |
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
docker compose run --rm php composer coverage   # line coverage, 95% minimum
```

The `pre-commit` and `pre-push` hooks run [gitleaks](https://github.com/gitleaks/gitleaks) (also through Docker) and block commits and pushes that contain secrets, such as an IXC token. Enable them once per clone:

```sh
git config core.hooksPath .githooks
```

CI runs the same scan over the whole history.

The fixtures in `tests/Fixtures/` are real, anonymized responses, captured with `docker compose run --rm php composer capture` against the instance set in `.env` (`IXC_HOST`, `IXC_TOKEN`, `IXC_INSECURE`). The capture creates, changes and deletes a client: use a disposable instance only.

## License

MIT
