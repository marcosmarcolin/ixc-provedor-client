# Development

[Português](development.md) | **English**

[← Back to the README](../README.en.md)

To contribute, see also [CONTRIBUTING.md](../CONTRIBUTING.md) (in Portuguese).

## Tests and linters

Everything runs in Docker (PHP 7.4 by default):

```sh
docker compose run --rm php composer install
docker compose run --rm php vendor/bin/phpunit
docker compose run --rm php composer lint
docker compose run --rm php composer coverage   # line coverage, 95% minimum
```

To try another PHP version, set `PHP_VERSION`:

```sh
PHP_VERSION=8.4 docker compose build php && PHP_VERSION=8.4 docker compose run --rm php vendor/bin/phpunit
```

## Secrets

The `pre-commit` and `pre-push` hooks run [gitleaks](https://github.com/gitleaks/gitleaks) (also through Docker) and block commits and pushes that contain secrets, such as an IXC token. Enable them once per clone:

```sh
git config core.hooksPath .githooks
```

CI runs the same scan over the whole history.

## Fixtures

The fixtures in `tests/Fixtures/` are real, anonymized responses, captured with `docker compose run --rm php composer capture` against the instance set in `.env` (`IXC_HOST`, `IXC_TOKEN`, `IXC_INSECURE`). The capture creates, changes and deletes a client: use a disposable instance only.
