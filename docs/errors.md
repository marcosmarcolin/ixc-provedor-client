# Erros

**Português** | [English](errors.en.md)

[← Voltar ao README](../README.md)

A maioria dos erros da API vem com HTTP 200 e `{"type":"error"}` no corpo. O pacote olha o corpo, não só o status, e transforma cada falha numa exceção.

## Exceções

Toda exceção de uma requisição implementa `Marcolin\IxcProvedor\Exception\IxcException`:

| Exceção | Quando |
|---|---|
| `ApiErrorException` | a API respondeu `{"type":"error"}` (ou `{"tipo":"error"}`, em alguns PATCH), o que costuma vir com HTTP 200, ou um status HTTP de erro |
| `NotFoundException` | `update()` de um registro que não existe |
| `UnexpectedResponseException` | a resposta não é a esperada para a rota, como uma página HTML de falha |
| `AuthenticationException` | HTTP 401: token recusado (segredo errado ou usuário inexistente) |
| `TransportException` | a requisição não teve resposta (DNS, conexão, TLS, timeout) |

## Mensagens

A mensagem de um `ApiErrorException` é o texto da própria API, em português, e pode ser mostrada ao usuário. As mensagens que a API manda com codificação dupla, como `nÃ£o` no lugar de `não` nas recusas de acesso por IP ou horário, chegam corrigidas:

```php
try {
    // ...
} catch (ApiErrorException $e) {
    $e->getMessage();   // "Preencha o campo Telefone Celular; CPF/CNPJ obrigatório!"
    $e->messages();     // ["Preencha o campo Telefone Celular", "CPF/CNPJ obrigatório!"]
    $e->statusCode();   // 200
}
```

`apiMessage()` traz o texto como a API mandou, sem correção. As mensagens geradas pelo próprio pacote (argumento inválido, falha de transporte ou de autenticação) são em inglês e nunca incluem o endereço nem o token.
