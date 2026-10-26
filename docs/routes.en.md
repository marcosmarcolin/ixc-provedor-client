# Routes

[Português](routes.md) | **English**

[← Back to the README](../README.en.md)

In IXC's model, a **route** is one of the system's forms (`cliente`, `cliente_contrato`, `fn_areceber`...), with listing and records: the API validates a request as the form validates its screen. Actions such as activating a contract or generating a bank slip are [resources](resources.en.md).

- [Listing](#listing)
- [Find, insert, update and delete](#find-insert-update-and-delete)
- [Files](#files)
- [Dates](#dates)
- [Caveats](#caveats)

## Listing

```php
use Marcolin\IxcProvedor\Query;

$clients = $ixc->route('cliente');

// One page
$page = $clients->list(
    Query::where('razao', 'L', 'Silva')     // L: contains
        ->andWhere('ativo', '=', 'S')       // extra conditions (grid_param)
        ->orderBy('id', 'desc')
        ->withPerPage(50)
);
$page->total();   // records matching the query, across all pages
$page->rows();    // the rows of this page

// Every page
foreach ($clients->each(Query::where('ativo', '=', 'S')) as $client) {
    // ...
}
```

- Fields may be given without the route name: `razao` becomes `cliente.razao`. Operators are the API's, such as `=`, `!=`, `>` and `L` (contains).
- Every value comes back as a string, ids included.

## Find, insert, update and delete

```php
$client = $clients->find('42');                           // array or null
$id = $clients->create($row);                             // id of the new record
$clients->update('42', ['obs' => 'Changed by the API']);  // changes only these fields
$clients->delete('42');
$clients->delete('42', '43', '44');                       // several in one request
```

- **Insert:** the API validates the whole form. Build the row from an existing record (`find`) or the wiki's full template and change only what is needed.
- **Update:** `update()` reads the record, applies the changes and writes the whole record back, because the API validates every field (two requests). For a missing id it throws `NotFoundException`. Fields the listing returns empty but the write requires must go in the changes, such as `tipo_recebimento` on `fn_areceber`.
- **Write a full row:** `replace($id, $row)` writes it directly, without reading first; `patch($id, $row)` does the same with the `PATCH` method, which the API also validates as the whole record.
- **Delete several:** in a `delete()` of several ids, the missing ones are skipped.

## Files

Files go in the form's file field, in a `create()`; the package sends the row as multipart, the only format in which the API takes files:

```php
use Marcolin\IxcProvedor\Attachment;

$ixc->route('cliente_arquivos')->create(
    ['id_cliente' => '42', 'descricao' => 'Signed contract'],
    ['local_arquivo' => Attachment::fromPath('/tmp/contrato.pdf', 'application/pdf')]
);
```

`Attachment::fromContents()` does the same with contents already in memory.

## Dates

Listings return `Y-m-d`, but writes expect `d/m/Y`. A `Y-m-d` date is saved blank or wrong with a success answer (`cliente`), or refused with an error (`cliente_contrato`, `fn_areceber`). So the package converts `Y-m-d` to `d/m/Y` on every write: dates can be sent as the listing returned them.

## Caveats

**False successes:** the API answers success to `replace()` of an id that does not exist, and to `delete()` too on most routes. On some, such as `cliente_arquivos`, deleting a missing id throws `ApiErrorException`. Success does not mean the record existed; when that matters, use `update()` or check with `find()`.
