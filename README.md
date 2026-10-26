# ixc-provedor-client

**Português** | [English](README.en.md)

[![CI](https://github.com/marcosmarcolin/ixc-provedor-client/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/marcosmarcolin/ixc-provedor-client/actions/workflows/ci.yml)
[![Packagist Version](https://img.shields.io/packagist/v/marcosmarcolin/ixc-provedor-client)](https://packagist.org/packages/marcosmarcolin/ixc-provedor-client)
[![PHP Version](https://img.shields.io/packagist/dependency-v/marcosmarcolin/ixc-provedor-client/php)](https://packagist.org/packages/marcosmarcolin/ixc-provedor-client)
[![Downloads](https://img.shields.io/packagist/dt/marcosmarcolin/ixc-provedor-client)](https://packagist.org/packages/marcosmarcolin/ixc-provedor-client/stats)
[![License](https://img.shields.io/packagist/l/marcosmarcolin/ixc-provedor-client)](LICENSE)

Cliente PHP tipado para a API (webservice) do **IXC Provedor**, da IXCSoft: liste, cadastre e altere registros e chame as ações do sistema sem se preocupar com as pegadinhas da API.

```php
$ixc = Client::create('https://ixc.seuprovedor.com.br', '6:SEU_TOKEN');

foreach ($ixc->route('cliente')->each(Query::where('ativo', '=', 'S')) as $cliente) {
    echo $cliente['razao'], PHP_EOL;
}

$ixc->route('cliente')->update('42', ['obs' => 'Alterado pela API']);
$ixc->resource(Resources::DESBLOQUEIO_CONFIANCA)->list(['id' => '42']);
```

> Construído com base na API pública do IXC Provedor, documentada em [wikiapiprovedor.ixcsoft.com.br](https://wikiapiprovedor.ixcsoft.com.br).

## Por que usar

- **Rotas e recursos**, no modelo do próprio IXC: listagem com filtros, paginação automática com `each()`, busca, cadastro, alteração e exclusão (de vários ids numa requisição), e constantes para todos os recursos documentados na wiki.
- **Datas que não se perdem:** a listagem devolve `Y-m-d`, a escrita espera `d/m/Y` e, sem conversão, grava vazio ou errado sem avisar. O pacote converte sozinho.
- **Atualizações parciais seguras:** `update()` lê o registro, aplica só o que mudou e grava o registro completo, como a API exige. Num id inexistente, lança exceção em vez do falso sucesso da API.
- **Erros viram exceções:** a API responde erro com HTTP 200; aqui ele vira `ApiErrorException`, com a mensagem em português corrigida e pronta para mostrar ao usuário.
- **Upload de arquivos** em multipart, o único formato que a API aceita.
- **Leve e compatível:** PHP 7.4 a 8.x, cliente HTTP próprio sobre o curl ou qualquer cliente PSR-18 (Guzzle, Symfony), sem framework.
- **Testado contra uma instância real:** o comportamento documentado vem de respostas capturadas do IXC Provedor, guardadas como fixtures anonimizadas.

## Requisitos

- PHP 7.4 ou superior, com a extensão `curl`
- Uma implementação das factories PSR-17, como `nyholm/psr7` ou `guzzlehttp/psr7`

## Instalação

```sh
composer require marcosmarcolin/ixc-provedor-client nyholm/psr7
```

## Primeiros passos

Gere o token em **Configurações > Usuários**, num usuário com **Permite acesso ao webservice** marcado, e passe o endereço da instância, sem `/webservice/v1`:

```php
use Marcolin\IxcProvedor\Client;
use Marcolin\IxcProvedor\Exception\ApiErrorException;
use Marcolin\IxcProvedor\Query;
use Marcolin\IxcProvedor\Resources;

$ixc = Client::create('https://ixc.seuprovedor.com.br', '6:SEU_TOKEN');

// Rotas: os formulários do sistema
$clientes = $ixc->route('cliente');
$pagina = $clientes->list(Query::where('razao', 'L', 'Silva')->withPerPage(50));
$cliente = $clientes->find('42');   // array ou null

// Recursos: as ações do sistema
$protocolo = $ixc->resource(Resources::GERAR_PROTOCOLO_ATENDIMENTO)->list()->json();

// Erros
try {
    $clientes->update('42', ['obs' => 'Alterado pela API']);
} catch (ApiErrorException $e) {
    $e->getMessage();   // "Preencha o campo Telefone Celular; CPF/CNPJ obrigatório!"
}
```

## Documentação

| Guia | O que tem |
|---|---|
| [Configuração](docs/configuration.md) | token, certificado autoassinado, outro cliente HTTP (Guzzle, Symfony) e versão da API |
| [Rotas](docs/routes.md) | listar, filtrar e paginar; buscar, inserir, atualizar e excluir; arquivos, datas e falsos sucessos |
| [Recursos](docs/resources.md) | chamar as ações da wiki, ler a resposta e os formatos que cada recurso devolve |
| [Erros](docs/errors.md) | as exceções e quando cada uma é lançada, e as mensagens da API |
| [Desenvolvimento](docs/development.md) | testes, linters, scan de segredos e captura de fixtures, tudo em Docker |

Veja também o [CHANGELOG](CHANGELOG.md), o guia de [contribuição](CONTRIBUTING.md) e a [política de segurança](SECURITY.md): vulnerabilidades são reportadas em particular.

## Licença

MIT
