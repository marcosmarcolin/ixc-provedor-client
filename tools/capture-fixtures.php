<?php

/**
 * Captures real responses from the IXC Provedor instance configured in .env as fixtures.
 *
 * Usage (Docker only): docker compose run --rm php composer capture
 * Raw responses go to tests/Fixtures/raw/ (gitignored); anonymized ones to tests/Fixtures/.
 * The write scenarios create, change and delete a client: point it at a disposable instance only.
 */

declare(strict_types=1);

use Marcolin\IxcProvedor\Tools\Anonymizer;
use Marcolin\IxcProvedor\Tools\FixtureRecorder;
use Marcolin\IxcProvedor\Tools\HttpCapture;
use Marcolin\IxcProvedor\Tools\Requests;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);
$env = is_file($root . '/.env') ? parse_ini_file($root . '/.env', false, INI_SCANNER_RAW) : [];
$setting = static function (string $name) use ($env): string {
    $value = getenv($name);
    if (is_string($value) && $value !== '') {
        return $value;
    }

    return is_array($env) && isset($env[$name]) && is_string($env[$name]) ? $env[$name] : '';
};
if ($setting('IXC_HOST') === '' || $setting('IXC_TOKEN') === '') {
    fwrite(STDERR, "Set IXC_HOST and IXC_TOKEN in .env.\n");
    exit(1);
}

$recorder = new FixtureRecorder(
    new HttpCapture($setting('IXC_HOST'), $setting('IXC_TOKEN'), $setting('IXC_INSECURE') === '1'),
    new Anonymizer(),
    $root . '/tests/Fixtures',
    STDOUT
);
$userId = explode(':', $setting('IXC_TOKEN'), 2)[0];
$fakeSecret = str_repeat('0', 64);
$json = ['Content-Type' => 'application/json'];
$listing = ['ixcsoft' => 'listar', 'Content-Type' => 'application/json'];
$clients = 'webservice/v1/cliente';

// Reads.
$recorder->record('list_page', 'POST', $clients, $listing, Requests::query('cliente.id', '>', '0'));
$recorder->record(
    'list_page_json_response',
    'POST',
    $clients,
    $listing + ['ixc-type-response' => 'application/json'],
    Requests::query('cliente.id', '>', '0')
);
$recorder->record('list_empty', 'POST', $clients, $listing, Requests::query('cliente.id', '=', '0'));
$recorder->record('list_grid_param', 'POST', $clients, $listing, Requests::query('cliente.id', '>', '0', [
    'grid_param' => (string) json_encode([['TB' => 'cliente.ativo', 'OP' => '=', 'P' => 'S']]),
]));
$recorder->record(
    'list_form_data',
    'POST',
    $clients,
    ['ixcsoft' => 'listar'],
    Requests::params('cliente.id', '>', '0')
);
$recorder->record('list_get_method', 'GET', $clients, $listing, Requests::query('cliente.id', '>', '0'));
$recorder->record(
    'list_invalid_field',
    'POST',
    $clients,
    $listing,
    Requests::query('cliente.campo_inexistente', '=', '1')
);
$recorder->record(
    'error_unknown_resource',
    'POST',
    'webservice/v1/tabela_inexistente',
    $listing,
    Requests::query('tabela_inexistente.id', '>', '0')
);
$recorder->record(
    'error_unknown_version',
    'POST',
    'webservice/v2/cliente',
    $listing,
    Requests::query('cliente.id', '>', '0')
);
$recorder->record(
    'error_wrong_secret',
    'POST',
    $clients,
    $listing,
    Requests::query('cliente.id', '>', '0'),
    $userId . ':' . $fakeSecret
);
$recorder->record(
    'error_unknown_user',
    'POST',
    $clients,
    $listing,
    Requests::query('cliente.id', '>', '0'),
    '999999:' . $fakeSecret
);
$cities = $recorder->record(
    'list_cidade',
    'POST',
    'webservice/v1/cidade',
    $listing,
    Requests::query('cidade.id', '>', '0')
);

// Writes, on a client created here and deleted at the end.
$cityId = Requests::firstId($cities['body']);
$client = [
    'ativo' => 'S',
    'tipo_pessoa' => 'F',
    'razao' => 'Cliente de teste ixc-provedor-client',
    'cnpj_cpf' => '529.982.247-25',
    'endereco' => 'Rua de Teste',
    'numero' => '1',
    'bairro' => 'Centro',
    'cidade' => $cityId !== '' ? $cityId : '1',
    'tipo_localidade' => 'U',
    'dica_imposto_retido_cliente' => 'N',
];

$recorder->record('insert_invalid', 'POST', $clients, $json, (string) json_encode(['ativo' => 'S']));
$id = Requests::firstId($recorder->record('insert', 'POST', $clients, $json, (string) json_encode($client))['body']);
if ($id === '') {
    fwrite(STDERR, "The insert returned no id; skipping the rest. See tests/Fixtures/raw/insert.json.\n");
    exit(1);
}
$one = $clients . '/' . $id;
$recorder->record('find', 'POST', $clients, $listing, Requests::query('cliente.id', '=', $id));
$recorder->record('update', 'PUT', $one, $json, (string) json_encode(['obs' => 'alterado pelo PUT'] + $client));
$recorder->record('update_invalid', 'PUT', $one, $json, (string) json_encode(['razao' => ''] + $client));
$recorder->record('patch', 'PATCH', $one, $json, (string) json_encode(['obs' => 'alterado pelo PATCH']));
$recorder->record('update_not_found', 'PUT', $clients . '/999999999', $json, (string) json_encode($client));
$recorder->record('delete', 'DELETE', $one);
$recorder->record('delete_not_found', 'DELETE', $one);
