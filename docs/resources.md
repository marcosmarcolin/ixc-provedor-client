# Recursos

[← Voltar ao README](../README.md)

Os recursos ("Recursos da API" na [wiki](https://wikiapiprovedor.ixcsoft.com.br)) são as ações do sistema, como ativar um contrato, gerar um boleto ou fechar uma ordem de serviço. São chamados pelo nome, com os parâmetros que a wiki documenta para cada um. Para listar e cadastrar registros, veja as [rotas](routes.md).

- [Chamar um recurso](#chamar-um-recurso)
- [A resposta](#a-resposta)
- [Formatos de resposta](#formatos-de-resposta)

## Chamar um recurso

`list()` equivale ao `$api->get()` dos exemplos da wiki, e `post()` ao `$api->post()`. Use o mesmo que a wiki usa no exemplo do recurso.

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
```

Os parâmetros vão como foram passados: as datas já vão no formato da wiki (`d/m/Y`).

## A resposta

```php
$resposta->status();
$resposta->body();   // a resposta crua: JSON, texto, base64 ou vazia
$resposta->json();   // decodificada; lança UnexpectedResponseException se não for JSON
$resposta->message();    // a mensagem da API, como "Contrato ativado com sucesso!"; vazia se não houver
$resposta->messages();   // as mensagens uma a uma, quando a API manda várias separadas por <br />
```

Erros de regra de negócio (`{"type":"error"}`, como "contrato já ativo") viram [`ApiErrorException`](errors.md).

## Formatos de resposta

Fora os erros, cada recurso responde num formato próprio, e alguns respondem sucesso mesmo para um id inexistente. Confira o retorno de cada um. Alguns exemplos observados:

| Recurso | Resposta |
|---|---|
| `gerar_protocolo_atendimento` | o protocolo como string JSON |
| `get_boleto` | os dados em JSON (`tipo_boleto` `dados`), o PDF em base64 (`arquivo` com `base64` `S`) ou o PDF binário (`base64` `N`); corpo vazio para fatura inexistente |
| `desconectar_clientes` | o resultado vem dentro de `msg[]` (`{"msg":[{"type":"error",...}]}`), sem `type` no topo, então nunca vira exceção |
| `imprimir_nota` | uma página HTML quando a nota não existe |
