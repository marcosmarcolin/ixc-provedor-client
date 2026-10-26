# Configuration

[Português](configuration.md) | **English**

[← Back to the README](../README.en.md)

- [Token](#token)
- [Instance address](#instance-address)
- [Certificate](#certificate)
- [Another HTTP client](#another-http-client)
- [API version](#api-version)

## Token

The token is generated in **Configurações > Usuários**, on a user with **Permite acesso ao webservice** checked, and has the form `<user id>:<secret>`. The user's group defines the networks it may connect from: from anywhere else, the API answers an error.

```php
use Marcolin\IxcProvedor\Client;

$ixc = Client::create('https://ixc.yourisp.com.br', '6:YOUR_TOKEN');
```

The token never shows up in exception messages or in the client's `var_dump()`.

## Instance address

Pass only the instance's address, without `/webservice/v1`: the wiki examples include that path, but here it is rejected.

## Certificate

The instance's certificate is verified. To accept a self-signed certificate, pass `true` as the third parameter, as the official client's `$selfSigned`:

```php
$ixc = Client::create('https://192.168.0.10', '6:YOUR_TOKEN', true);
```

## Another HTTP client

The package has its own HTTP client, on curl. To use another PSR-18 client, such as Guzzle or Symfony HttpClient, pass it to `withHttpClient()`; its own settings then decide the certificate, timeouts and proxy:

```php
$ixc = Client::withHttpClient('https://ixc.yourisp.com.br', '6:YOUR_TOKEN', new \GuzzleHttp\Client(['verify' => false]));
```

Retries, caching and rate limiting belong in that client too (or in its middleware), not in the package.

## API version

The routes live under `https://HOST/webservice/v1`. Today `v1` is the only version and the default; it can be passed explicitly:

```php
use Marcolin\IxcProvedor\ApiVersion;

$ixc = Client::create('https://ixc.yourisp.com.br', '6:YOUR_TOKEN', false, ApiVersion::v1());
```
