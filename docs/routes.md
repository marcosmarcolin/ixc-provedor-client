# Rotas

[← Voltar ao README](../README.md)

No modelo do IXC, uma **rota** é um formulário do sistema (`cliente`, `cliente_contrato`, `fn_areceber`...), com listagem e cadastro: a API valida a requisição como o formulário valida a tela. Ações como ativar um contrato ou gerar um boleto são [recursos](resources.md).

- [Listar](#listar)
- [Buscar, inserir, atualizar e excluir](#buscar-inserir-atualizar-e-excluir)
- [Arquivos](#arquivos)
- [Datas](#datas)
- [Cuidados](#cuidados)

## Listar

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
```

- Os campos podem ir sem o nome da rota: `razao` vira `cliente.razao`. Os operadores são os da API, como `=`, `!=`, `>` e `L` (contém).
- Todo valor volta como string, inclusive os ids.

## Buscar, inserir, atualizar e excluir

```php
$cliente = $clientes->find('42');                          // array ou null
$id = $clientes->create($linha);                           // id do novo registro
$clientes->update('42', ['obs' => 'Alterado pela API']);   // muda só esses campos
$clientes->delete('42');
$clientes->delete('42', '43', '44');                       // vários numa requisição só
```

- **Inserir:** a API valida o formulário inteiro. Monte a linha a partir de um registro existente (`find`) ou do modelo completo da wiki e mude só o necessário.
- **Atualizar:** `update()` lê o registro, aplica as mudanças e grava o registro completo, porque a API valida todos os campos (são duas requisições). Num id inexistente, lança `NotFoundException`. Campos que a listagem devolve vazios mas a escrita exige precisam ir nas mudanças, como `tipo_recebimento` em `fn_areceber`.
- **Gravar a linha completa:** `replace($id, $linha)` grava direto, sem ler antes; `patch($id, $linha)` faz o mesmo com o método `PATCH`, que a API também valida como o registro completo.
- **Excluir vários:** num `delete()` com vários ids, os que não existem são ignorados.

## Arquivos

Arquivos vão no campo de arquivo do formulário, num `create()`; o pacote envia a linha como multipart, o único formato em que a API aceita arquivos:

```php
use Marcolin\IxcProvedor\Attachment;

$ixc->route('cliente_arquivos')->create(
    ['id_cliente' => '42', 'descricao' => 'Contrato assinado'],
    ['local_arquivo' => Attachment::fromPath('/tmp/contrato.pdf', 'application/pdf')]
);
```

`Attachment::fromContents()` faz o mesmo com o conteúdo já em memória.

## Datas

A listagem devolve `Y-m-d`, mas a escrita espera `d/m/Y`. Uma data em `Y-m-d` é gravada vazia ou errada com resposta de sucesso (`cliente`), ou recusada com erro (`cliente_contrato`, `fn_areceber`). Por isso o pacote converte `Y-m-d` para `d/m/Y` em toda escrita: pode mandar as datas como vieram da listagem.

## Cuidados

**Falsos sucessos:** a API responde sucesso a `replace()` de um id que não existe, e a `delete()` também na maioria das rotas. Em algumas, como `cliente_arquivos`, excluir um id inexistente lança `ApiErrorException`. Sucesso não quer dizer que o registro existia; quando isso importa, use `update()` ou confira com `find()`.
