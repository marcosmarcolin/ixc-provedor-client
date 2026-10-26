# Como contribuir

Obrigado pelo interesse! Este guia explica o que o pacote aceita e como preparar uma mudança.

Quem participa do projeto segue o [Código de Conduta](CODE_OF_CONDUCT.md).

## Antes de começar

- **Bug ou ideia:** abra uma [issue](https://github.com/marcosmarcolin/ixc-provedor-client/issues/new/choose) antes de escrever código, principalmente para mudanças grandes. Assim dá para combinar a abordagem antes do trabalho.
- **Vulnerabilidade:** não abra issue pública. Siga o [SECURITY.md](SECURITY.md).
- **Escopo:** o pacote é um cliente fino e tipado da API, com rotas e recursos como o IXC Provedor os define. Ficam de fora: modelos ou DTOs por rota, dependência de Guzzle, Symfony ou outro framework, e retry, cache ou rate limit (esses ficam no cliente PSR-18 passado em `Client::withHttpClient()`).

## Só a API pública

O pacote é apenas um consumidor da API. Nada do código-fonte do IXC Provedor entra no repositório, na documentação, nos testes, nos commits ou nos PRs: nenhum arquivo, nome interno ou comportamento conhecido só pela leitura do código.

Comportamento se documenta só pelo que se observa na API, na ordem:

1. respostas de uma instância real, capturadas como fixtures anonimizadas em `tests/Fixtures/`;
2. a [wiki oficial da API](https://wikiapiprovedor.ixcsoft.com.br);
3. o cliente oficial `IXCsoft\WebserviceClient`.

Na dúvida, consulte a wiki ou capture uma resposta real, nunca implemente de memória.

## Ambiente

Tudo roda em Docker, nunca no PHP da sua máquina (PHP 7.4 por padrão; `PHP_VERSION` troca a versão):

```sh
git config core.hooksPath .githooks   # uma vez por clone: gitleaks antes de cada commit e push
docker compose run --rm php composer install
docker compose run --rm php vendor/bin/phpunit
docker compose run --rm php composer lint
docker compose run --rm php composer coverage
PHP_VERSION=8.5 docker compose build php && PHP_VERSION=8.5 docker compose run --rm php vendor/bin/phpunit
```

## O que uma mudança precisa ter

- **Testes:** comportamento novo vem com testes, e uma correção vem com um teste que falha sem ela. O HTTP é simulado com `php-http/mock-client`; os testes nunca acessam a rede.
- **Qualidade:** PHPUnit, phpcs (PSR-12) e PHPStan no nível `max` passando, e cobertura de linhas de pelo menos 95%, em todas as versões do PHP 7.4 à mais recente.
- **Compatível com PHP 7.4:** sem union types, `mixed`, `match`, argumentos nomeados, promoção de construtor, nullsafe, retorno `static`, enums ou `readonly`.
- **Tipado:** `declare(strict_types=1);` em todo arquivo, parâmetros, retornos e propriedades tipados, classes `final` e objetos de valor imutáveis.
- **Sem segredos:** nenhum token, host ou dado real de cliente em código, fixtures, mensagens de erro ou histórico. O gitleaks roda nos hooks e no CI; nunca use `--no-verify`.
- **Documentação em dia:** o `README.md`, os guias de `docs/` e o `CHANGELOG.md`, na seção `Unreleased`.

## Commits e pull requests

- Commits seguem [Conventional Commits](https://www.conventionalcommits.org/) (`feat:`, `fix:`, `docs:`, `test:`, `refactor:`, `chore:`, `ci:`), com `!` ou o rodapé `BREAKING CHANGE:` para mudanças incompatíveis.
- Código, comentários, mensagens de commit e PRs são em inglês; a documentação para usuários é em português.
- Um assunto por PR, com o CI verde e uma descrição do que mudou e de como foi verificado. O modelo de PR já traz esses campos.

## Fixtures

As fixtures são capturadas com `docker compose run --rm php composer capture` contra a instância do `.env` (`IXC_HOST`, `IXC_TOKEN`, `IXC_INSECURE`). A captura cria, altera e apaga registros: use só uma instância descartável. As respostas brutas ficam em `tests/Fixtures/raw/`, ignorada pelo git; só as anonimizadas são commitadas, depois de conferir que nenhum valor real sobrou.
