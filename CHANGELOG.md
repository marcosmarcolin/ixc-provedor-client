# Changelog

All notable changes to this package are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and versions follow [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

- `Response::message()` and `messages()`: the `message` of a resource's answer as plain text, such as `"Contrato ativado com sucesso!"`, split on `<br />` like `ApiErrorException`'s; empty when the answer has none.
- `Route::delete()` takes several ids, `delete('18', '19')`, and deletes them in one request, as the API accepts them comma-separated.

### Fixed

- The messages the API sends double-encoded, such as the access refusals by IP or hour (`Seu IP nÃ£o está liberado...`), are repaired in `ApiErrorException::getMessage()` and `messages()`; `apiMessage()` stays as sent.
- A PATCH error answered as `{"tipo":"error","valor":...}`, as `cliente` and `cliente_contrato` do for an id that does not exist, now raises `ApiErrorException` with the API's message, instead of an `UnexpectedResponseException`.

## [0.1.0] - 2026-10-25

First release: a client for the public IXC Provedor API (`/webservice/v1`), covering the routes and resources its wiki documents.

### Added

- `Client::create($host, $token, $selfSigned = false)` on a built-in HTTP client (ext-curl): as in the official client, the certificate is verified unless `$selfSigned` is `true`.
- `Client::withHttpClient($host, $token, $httpClient)` for any PSR-18 client, such as Guzzle or Symfony HttpClient.
- Routes (the system's forms) through `Client::route($name)`:
  - `list()` with `Query` (condition, `grid_param` filters, paging and sorting) and `each()` over every page;
  - `find()`, `create()` (also with files, as multipart form data, through `Attachment`), `update()`, `replace()`, `patch()` and `delete()`;
  - `update()` reads the record, applies the changes and writes the whole record back, throwing `NotFoundException` for a missing id;
  - writes send `Y-m-d` dates as `d/m/Y`, since the API saves a `Y-m-d` date blank or corrupted while answering success.
- Resources (the system's actions) through `Client::resource($name)`, with `list()` and `post()` as the wiki calls them and the raw `Response` (`status()`, `header()`, `body()`, `json()`); `Resources` names the 17 resources the wiki documents.
- Exceptions under `IxcException`: `ApiErrorException` (the API's `{"type":"error"}`, usually with HTTP 200, with the API's own Portuguese message), `AuthenticationException`, `TransportException`, `NotFoundException` and `UnexpectedResponseException`.
- `ApiVersion`, with `v1` as the only supported version.
- PHP 7.4 to 8.5.

[Unreleased]: https://github.com/marcosmarcolin/ixc-provedor-client/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/marcosmarcolin/ixc-provedor-client/releases/tag/v0.1.0
