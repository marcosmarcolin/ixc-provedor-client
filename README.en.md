# ixc-provedor-client

[Português](README.md) | **English**

PHP client for the **IXC Provedor** API (webservice), by IXCSoft.

> **Under development**, with no published version yet; see the roadmap in [CLAUDE.md](CLAUDE.md).

## Requirements

- PHP 7.4 or later
- A PSR-18 HTTP client with PSR-17 factories. Guzzle 7 provides both.

## Installation

```sh
composer require marcosmarcolin/ixc-provedor-client guzzlehttp/guzzle
```

Any PSR-18 client works (for example, `symfony/http-client` with `nyholm/psr7`); the installed one is discovered automatically.

## Getting started

The token is generated in **Configurações > Usuários**, on a user with **Permite acesso ao webservice** checked, and has the form `<user id>:<secret>`. The user's group defines the networks it may connect from: from anywhere else, the API answers an error.

```php
use Marcolin\IxcProvedor\Client;

$ixc = Client::create('https://ixc.yourisp.com.br', '6:YOUR_TOKEN');
```

Pass only the instance's address, without `/webservice/v1`: the wiki examples include that path, but here it is rejected.

With a self-signed certificate, pass an HTTP client configured to accept it:

```php
$http = new \GuzzleHttp\Client(['verify' => false]);
$ixc = Client::create('https://192.168.0.10', '6:YOUR_TOKEN', $http);
```

## Tables

```php
use Marcolin\IxcProvedor\Query;

$clients = $ixc->table('cliente');

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

$ixc->table('cliente_arquivos')->create(
    ['id_cliente' => '42', 'descricao' => 'Signed contract'],
    ['local_arquivo' => Attachment::fromPath('/tmp/contrato.pdf', 'application/pdf')]
);
```

- Fields may be given without the table name: `razao` becomes `cliente.razao`. Operators are the API's, such as `=`, `!=`, `>` and `L` (contains).
- Every value comes back as a string, ids included.
- **Insert:** the API validates the whole form. Build the row from an existing record (`find`) or the wiki's full template and change only what is needed.
- **Dates:** listings return `Y-m-d`, but writes expect `d/m/Y`. A `Y-m-d` date is saved blank or wrong, and the API still answers success. So the package converts `Y-m-d` to `d/m/Y` on every write; dates can be sent as the listing returned them.
- **Update:** `update()` reads the record, applies the changes and writes the whole record back, because the API validates every field (two requests). For a missing id it throws `NotFoundException`. To write a full row directly, use `replace($id, $row)`; `patch($id, $row)` does the same with the `PATCH` method, which the API also validates as the whole record.
- **False successes:** the API answers success to `replace()` and `delete()` of an id that does not exist.

## Resources

The API resources ("Recursos da API" in the [wiki](https://wikiapiprovedor.ixcsoft.com.br)) are called by name, with the parameters the wiki documents for each. `list()` matches the wiki examples' `$api->get()`, and `post()` their `$api->post()`:

```php
$protocol = $ixc->resource('gerar_protocolo_atendimento')->list()->json();   // "20261024162331914000"

$response = $ixc->resource('desbloqueio_confianca')->list(['id' => '42']);

$response = $ixc->resource('su_oss_chamado_fechar')->post([
    'id_chamado' => '101',
    'data_inicio' => '02/12/2019 10:30:00',
    'data_final' => '04/12/2019 14:16:00',
    // ...
]);

$response->status();
$response->body();   // the raw answer: JSON, text, base64 or empty
$response->json();   // decoded; throws UnexpectedResponseException when it is not JSON
```

Each resource answers in its own format, and some answer success even for a missing id. Check each one's answer.

## API version

The routes live under `https://HOST/webservice/v1`. Today `v1` is the only version and the default; it can be passed explicitly:

```php
use Marcolin\IxcProvedor\ApiVersion;

$ixc = Client::create('https://ixc.yourisp.com.br', '6:YOUR_TOKEN', null, ApiVersion::v1());
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
```

The `pre-commit` and `pre-push` hooks run [gitleaks](https://github.com/gitleaks/gitleaks) (also through Docker) and block commits and pushes that contain secrets, such as an IXC token. Enable them once per clone:

```sh
git config core.hooksPath .githooks
```

CI runs the same scan over the whole history.

The fixtures in `tests/Fixtures/` are real, anonymized responses, captured with `docker compose run --rm php composer capture` against the instance set in `.env` (`IXC_HOST`, `IXC_TOKEN`, `IXC_INSECURE`). The capture creates, changes and deletes a client: use a disposable instance only.

## License

MIT
