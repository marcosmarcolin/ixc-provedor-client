# Resources

[Português](resources.md) | **English**

[← Back to the README](../README.en.md)

Resources ("Recursos da API" in the [wiki](https://wikiapiprovedor.ixcsoft.com.br)) are the system's actions, such as activating a contract, generating a bank slip or closing a service order. They are called by name, with the parameters the wiki documents for each. To list and write records, see [routes](routes.en.md).

- [Calling a resource](#calling-a-resource)
- [The answer](#the-answer)
- [Answer formats](#answer-formats)

## Calling a resource

`list()` matches the wiki examples' `$api->get()`, and `post()` their `$api->post()`. Use the one the wiki uses in the resource's example.

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
```

Parameters go as given: dates already go in the wiki's format (`d/m/Y`).

## The answer

```php
$response->status();
$response->body();   // the raw answer: JSON, text, base64 or empty
$response->json();   // decoded; throws UnexpectedResponseException when it is not JSON
$response->message();    // the API's message, such as "Contrato ativado com sucesso!"; empty when there is none
$response->messages();   // the messages one by one, when the API sends several separated by <br />
```

Business-rule errors (`{"type":"error"}`, such as "contract already active") become [`ApiErrorException`](errors.en.md).

## Answer formats

Apart from errors, each resource answers in its own format, and some answer success even for a missing id. Check each one's answer. Some answers seen:

| Resource | Answer |
|---|---|
| `gerar_protocolo_atendimento` | the protocol as a JSON string |
| `get_boleto` | the data as JSON (`tipo_boleto` `dados`), the PDF in base64 (`arquivo` with `base64` `S`) or the raw PDF (`base64` `N`); an empty body for a missing invoice |
| `desconectar_clientes` | the outcome comes inside `msg[]` (`{"msg":[{"type":"error",...}]}`), with no top-level `type`, so it never becomes an exception |
| `imprimir_nota` | an HTML page when the invoice does not exist |
