# Configuração

**Português** | [English](configuration.en.md)

[← Voltar ao README](../README.md)

- [Token](#token)
- [Endereço da instância](#endereço-da-instância)
- [Certificado](#certificado)
- [Outro cliente HTTP](#outro-cliente-http)
- [Versão da API](#versão-da-api)

## Token

O token é gerado em **Configurações > Usuários**, num usuário com **Permite acesso ao webservice** marcado, e tem o formato `<id do usuário>:<segredo>`. O grupo do usuário define de quais redes ele pode acessar: de fora delas, a API responde erro.

```php
use Marcolin\IxcProvedor\Client;

$ixc = Client::create('https://ixc.seuprovedor.com.br', '6:SEU_TOKEN');
```

O token nunca aparece nas mensagens de exceção nem no `var_dump()` do cliente.

## Endereço da instância

Passe só o endereço da instância, sem `/webservice/v1`: os exemplos da wiki incluem esse caminho, mas aqui ele é recusado.

## Certificado

O certificado da instância é verificado. Para aceitar um certificado autoassinado, passe `true` no terceiro parâmetro, como o `$selfSigned` do cliente oficial:

```php
$ixc = Client::create('https://192.168.0.10', '6:SEU_TOKEN', true);
```

## Outro cliente HTTP

O pacote usa um cliente HTTP próprio, sobre o curl. Para usar outro cliente PSR-18, como o Guzzle ou o Symfony HttpClient, passe-o em `withHttpClient()`; aí o certificado, os timeouts e o proxy são configurados nele:

```php
$ixc = Client::withHttpClient('https://ixc.seuprovedor.com.br', '6:SEU_TOKEN', new \GuzzleHttp\Client(['verify' => false]));
```

Retry, cache e rate limit também ficam nesse cliente (ou num middleware dele), não no pacote.

## Versão da API

As rotas ficam em `https://HOST/webservice/v1`. Hoje só existe a `v1`, que é o padrão; a versão pode ser passada explicitamente:

```php
use Marcolin\IxcProvedor\ApiVersion;

$ixc = Client::create('https://ixc.seuprovedor.com.br', '6:SEU_TOKEN', false, ApiVersion::v1());
```
