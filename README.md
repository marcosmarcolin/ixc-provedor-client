# ixc-provedor-client

**Português** | [English](README.en.md)

[![CI](https://github.com/marcosmarcolin/ixc-provedor-client/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/marcosmarcolin/ixc-provedor-client/actions/workflows/ci.yml)
[![Packagist Version](https://img.shields.io/packagist/v/marcosmarcolin/ixc-provedor-client)](https://packagist.org/packages/marcosmarcolin/ixc-provedor-client)
[![PHP Version](https://img.shields.io/packagist/dependency-v/marcosmarcolin/ixc-provedor-client/php)](https://packagist.org/packages/marcosmarcolin/ixc-provedor-client)
[![Downloads](https://img.shields.io/packagist/dt/marcosmarcolin/ixc-provedor-client)](https://packagist.org/packages/marcosmarcolin/ixc-provedor-client/stats)
[![License](https://img.shields.io/packagist/l/marcosmarcolin/ixc-provedor-client)](LICENSE)

> Construído com base na API pública do IXC Provedor, documentada em [wikiapiprovedor.ixcsoft.com.br](https://wikiapiprovedor.ixcsoft.com.br).

Cliente PHP para a API (webservice) do **IXC Provedor**, da IXCSoft.

## Requisitos

- PHP 7.4 ou superior, com a extensão `curl`
- Uma implementação das factories PSR-17, como `nyholm/psr7` ou `guzzlehttp/psr7`

## Instalação

```sh
composer require marcosmarcolin/ixc-provedor-client nyholm/psr7
```

## Primeiros passos

O token é gerado em **Configurações > Usuários**, num usuário com **Permite acesso ao webservice** marcado, e tem o formato `<id do usuário>:<segredo>`. O grupo do usuário define de quais redes ele pode acessar: de fora delas, a API responde erro.

```php
use Marcolin\IxcProvedor\Client;

$ixc = Client::create('https://ixc.seuprovedor.com.br', '6:SEU_TOKEN');
```

Passe só o endereço da instância, sem `/webservice/v1`: os exemplos da wiki incluem esse caminho, mas aqui ele é recusado.

### Certificado

O certificado da instância é verificado. Para aceitar um certificado autoassinado, passe `true` no terceiro parâmetro, como o `$selfSigned` do cliente oficial:

```php
$ixc = Client::create('https://192.168.0.10', '6:SEU_TOKEN', true);
```

### Outro cliente HTTP

O pacote usa um cliente HTTP próprio, sobre o curl. Para usar outro cliente PSR-18, como o Guzzle ou o Symfony HttpClient, passe-o em `withHttpClient()`; aí o certificado, os timeouts e o proxy são configurados nele:

```php
$ixc = Client::withHttpClient('https://ixc.seuprovedor.com.br', '6:SEU_TOKEN', new \GuzzleHttp\Client(['verify' => false]));
```

## Rotas

No modelo do IXC, uma **rota** é um formulário do sistema (`cliente`, `cliente_contrato`, `fn_areceber`...), com listagem e cadastro: a API valida a requisição como o formulário valida a tela. Ações como ativar um contrato ou gerar um boleto são **recursos** (próxima seção).

```php
use Marcolin\IxcProvedor\Query;

$clientes = $ixc->route('cliente');

// Uma página
$pagina = $clientes->list(
    Query::where('razao', 'L', 'Silva')     // L: contém
        ->andWhere('ativo', '=', 'S')       // condições extras (grid_param)
        ->orderBy('id', 'desc')
        ->withPerPage(50)
);
$pagina->total();   // registros que atendem a consulta, somando todas as páginas
$pagina->rows();    // as linhas desta página

// Todas as páginas
foreach ($clientes->each(Query::where('ativo', '=', 'S')) as $cliente) {
    // ...
}

$cliente = $clientes->find('42');                          // array ou null
$id = $clientes->create($linha);                           // id do novo registro
$clientes->update('42', ['obs' => 'Alterado pela API']);   // muda só esses campos
$clientes->delete('42');
$clientes->delete('42', '43', '44');                       // vários numa requisição só
```

Arquivos vão no campo de arquivo do formulário, num `create()`; o pacote envia a linha como multipart, o único formato em que a API aceita arquivos:

```php
use Marcolin\IxcProvedor\Attachment;

$ixc->route('cliente_arquivos')->create(
    ['id_cliente' => '42', 'descricao' => 'Contrato assinado'],
    ['local_arquivo' => Attachment::fromPath('/tmp/contrato.pdf', 'application/pdf')]
);
```

- Os campos podem ir sem o nome da rota: `razao` vira `cliente.razao`. Os operadores são os da API, como `=`, `!=`, `>` e `L` (contém).
- Todo valor volta como string, inclusive os ids.
- **Inserir:** a API valida o formulário inteiro. Monte a linha a partir de um registro existente (`find`) ou do modelo completo da wiki e mude só o necessário.
- **Datas:** a listagem devolve `Y-m-d`, mas a escrita espera `d/m/Y`. Uma data em `Y-m-d` é gravada vazia ou errada com resposta de sucesso (`cliente`), ou recusada com erro (`cliente_contrato`, `fn_areceber`). Por isso o pacote converte `Y-m-d` para `d/m/Y` em toda escrita; pode mandar as datas como vieram da listagem.
- **Atualizar:** `update()` lê o registro, aplica as mudanças e grava o registro completo, porque a API valida todos os campos (são duas requisições). Num id inexistente, lança `NotFoundException`. Campos que a listagem devolve vazios mas a escrita exige precisam ir nas mudanças, como `tipo_recebimento` em `fn_areceber`. Para gravar uma linha completa direto, use `replace($id, $linha)`; `patch($id, $linha)` faz o mesmo com o método `PATCH`, que a API também valida como o registro completo.
- **Falsos sucessos:** a API responde sucesso a `replace()` de um id que não existe, e a `delete()` também na maioria das rotas. Em algumas, como `cliente_arquivos`, excluir um id inexistente lança `ApiErrorException`. Num `delete()` com vários ids, os que não existem são ignorados.

## Recursos

Os recursos ("Recursos da API" na [wiki](https://wikiapiprovedor.ixcsoft.com.br)) são as ações do sistema, chamadas pelo nome com os parâmetros que a wiki documenta para cada um. `list()` equivale ao `$api->get()` dos exemplos da wiki, e `post()` ao `$api->post()`.

Os recursos documentados na wiki têm constantes em `Resources`, com o nome do título da wiki; qualquer outro nome pode ser passado como texto:

```php
use Marcolin\IxcProvedor\Resources;

$protocolo = $ixc->resource(Resources::GERAR_PROTOCOLO_ATENDIMENTO)->list()->json();   // "20261024162331914000"

$resposta = $ixc->resource(Resources::DESBLOQUEIO_CONFIANCA)->list(['id' => '42']);

$resposta = $ixc->resource('outro_recurso_12345')->post([...]);   // nome fora da lista

$resposta = $ixc->resource(Resources::FINALIZAR_ORDEM_SERVICO)->post([
    'id_chamado' => '101',
    'data_inicio' => '02/12/2019 10:30:00',
    'data_final' => '04/12/2019 14:16:00',
    // ...
]);

$resposta->status();
$resposta->body();   // a resposta crua: JSON, texto, base64 ou vazia
$resposta->json();   // decodificada; lança UnexpectedResponseException se não for JSON
$resposta->message();    // a mensagem da API, como "Contrato ativado com sucesso!"; vazia se não houver
$resposta->messages();   // as mensagens uma a uma, quando a API manda várias separadas por <br />
```

Erros de regra de negócio (`{"type":"error"}`, como "contrato já ativo") viram `ApiErrorException`. Fora isso, cada recurso responde num formato próprio, e alguns respondem sucesso mesmo para um id inexistente. Confira o retorno de cada um. Alguns exemplos observados:

- `gerar_protocolo_atendimento`: o protocolo como string JSON;
- `get_boleto`: os dados em JSON (`tipo_boleto` `dados`), o PDF em base64 (`arquivo` com `base64` `S`) ou o PDF binário (`base64` `N`); corpo vazio para fatura inexistente;
- `desconectar_clientes`: o resultado vem dentro de `msg[]` (`{"msg":[{"type":"error",...}]}`), sem `type` no topo, então nunca vira exceção;
- `imprimir_nota`: uma página HTML quando a nota não existe.

## Versão da API

As rotas ficam em `https://HOST/webservice/v1`. Hoje só existe a `v1`, que é o padrão; a versão pode ser passada explicitamente:

```php
use Marcolin\IxcProvedor\ApiVersion;

$ixc = Client::create('https://ixc.seuprovedor.com.br', '6:SEU_TOKEN', false, ApiVersion::v1());
```

## Erros

Toda exceção de uma requisição implementa `Marcolin\IxcProvedor\Exception\IxcException`:

| Exceção | Quando |
|---|---|
| `ApiErrorException` | a API respondeu `{"type":"error"}` (ou `{"tipo":"error"}`, em alguns PATCH), o que costuma vir com HTTP 200, ou um status HTTP de erro |
| `NotFoundException` | `update()` de um registro que não existe |
| `UnexpectedResponseException` | a resposta não é a esperada para a rota, como uma página HTML de falha |
| `AuthenticationException` | HTTP 401: token recusado (segredo errado ou usuário inexistente) |
| `TransportException` | a requisição não teve resposta (DNS, conexão, TLS, timeout) |

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

## Desenvolvimento

Para contribuir, veja o [CONTRIBUTING.md](CONTRIBUTING.md).

Tudo roda em Docker (PHP 7.4 por padrão):

```sh
docker compose run --rm php composer install
docker compose run --rm php vendor/bin/phpunit
docker compose run --rm php composer lint
docker compose run --rm php composer coverage   # cobertura de linhas, mínimo de 95%
```

Os hooks de `pre-commit` e `pre-push` rodam o [gitleaks](https://github.com/gitleaks/gitleaks) (também via Docker) e bloqueiam commits e pushes que contenham segredos, como um token do IXC. Ative-os uma vez por clone:

```sh
git config core.hooksPath .githooks
```

O CI roda o mesmo scan em todo o histórico.

As fixtures de `tests/Fixtures/` são respostas reais anonimizadas, capturadas com `docker compose run --rm php composer capture` contra a instância configurada no `.env` (`IXC_HOST`, `IXC_TOKEN`, `IXC_INSECURE`). A captura cria, altera e apaga um cliente: use só uma instância descartável.

## Segurança

Vulnerabilidades são reportadas em particular, como explica o [SECURITY.md](SECURITY.md).

## Licença

MIT
