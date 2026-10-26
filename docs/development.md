# Desenvolvimento

[← Voltar ao README](../README.md)

Para contribuir, veja também o [CONTRIBUTING.md](../CONTRIBUTING.md).

## Testes e linters

Tudo roda em Docker (PHP 7.4 por padrão):

```sh
docker compose run --rm php composer install
docker compose run --rm php vendor/bin/phpunit
docker compose run --rm php composer lint
docker compose run --rm php composer coverage   # cobertura de linhas, mínimo de 95%
```

Para testar outra versão do PHP, defina `PHP_VERSION`:

```sh
PHP_VERSION=8.4 docker compose build php && PHP_VERSION=8.4 docker compose run --rm php vendor/bin/phpunit
```

## Segredos

Os hooks de `pre-commit` e `pre-push` rodam o [gitleaks](https://github.com/gitleaks/gitleaks) (também via Docker) e bloqueiam commits e pushes que contenham segredos, como um token do IXC. Ative-os uma vez por clone:

```sh
git config core.hooksPath .githooks
```

O CI roda o mesmo scan em todo o histórico.

## Fixtures

As fixtures de `tests/Fixtures/` são respostas reais anonimizadas, capturadas com `docker compose run --rm php composer capture` contra a instância configurada no `.env` (`IXC_HOST`, `IXC_TOKEN`, `IXC_INSECURE`). A captura cria, altera e apaga um cliente: use só uma instância descartável.
