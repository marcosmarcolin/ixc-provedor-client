# Errors

[Português](errors.md) | **English**

[← Back to the README](../README.en.md)

Most API errors come with HTTP 200 and `{"type":"error"}` in the body. The package reads the body, not only the status, and turns every failure into an exception.

## Exceptions

Every exception from a request implements `Marcolin\IxcProvedor\Exception\IxcException`:

| Exception | When |
|---|---|
| `ApiErrorException` | the API answered `{"type":"error"}` (or `{"tipo":"error"}`, on some PATCHes), usually with HTTP 200, or an HTTP error status |
| `NotFoundException` | `update()` of a record that does not exist |
| `UnexpectedResponseException` | the answer is not what the route returns, such as an HTML failure page |
| `AuthenticationException` | HTTP 401: the token was rejected (wrong secret or unknown user) |
| `TransportException` | the request got no answer (DNS, connection, TLS, timeout) |

## Messages

An `ApiErrorException`'s message is the API's own text, in Portuguese, so it can be shown to users. Messages the API sends double-encoded, such as `nÃ£o` for `não` in access refusals by IP or hour, come repaired:

```php
try {
    // ...
} catch (ApiErrorException $e) {
    $e->getMessage();   // "Preencha o campo Telefone Celular; CPF/CNPJ obrigatório!"
    $e->messages();     // ["Preencha o campo Telefone Celular", "CPF/CNPJ obrigatório!"]
    $e->statusCode();   // 200
}
```

`apiMessage()` holds the text as the API sent it, unrepaired. Messages written by the package itself (invalid arguments, transport and authentication failures) are in English and never include the host or the token.
