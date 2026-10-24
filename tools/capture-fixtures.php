<?php

/**
 * Captures real responses from the IXC Provedor instance configured in .env as fixtures.
 *
 * Usage (Docker only): docker compose run --rm php composer capture [-- tables|resources]
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

$groups = array_slice($argv, 1);
$wants = static function (string $group) use ($groups): bool {
    return $groups === [] || in_array($group, $groups, true);
};

// Resources, called with ids that do not exist: their errors and formats need no data on the instance.
if ($wants('resources')) {
    $empty = '{}';
    $resource = static function (string $name): string {
        return 'webservice/v1/' . $name;
    };
    $recorder->record('resource_protocol_listar', 'POST', $resource('gerar_protocolo_atendimento'), $listing, $empty);
    $recorder->record('resource_protocol_post', 'POST', $resource('gerar_protocolo_atendimento'), $json, $empty);
    $protocol = $resource('gerar_protocolo_atendimento');
    $recorder->record('resource_protocol_form', 'POST', $protocol, ['ixcsoft' => 'listar'], []);
    $recorder->record(
        'resource_contract_status_missing',
        'POST',
        $resource('cliente_contrato_15464'),
        $listing,
        (string) json_encode(['id_contrato' => '999999999'])
    );
    $recorder->record(
        'resource_trust_unlock_missing',
        'POST',
        $resource('desbloqueio_confianca'),
        $listing,
        (string) json_encode(['id' => '999999999'])
    );
    $recorder->record('resource_boleto_missing', 'POST', $resource('get_boleto'), $listing, (string) json_encode([
        'boletos' => '999999999',
        'juro' => 'N',
        'multa' => 'N',
        'atualiza_boleto' => 'N',
        'tipo_boleto' => 'arquivo',
        'base64' => 'S',
    ]));
    $closeOrder = $resource('su_oss_chamado_fechar');
    $recorder->record('resource_close_order_missing', 'POST', $closeOrder, $json, (string) json_encode([
        'id_chamado' => '999999999',
        'data_inicio' => '02/12/2019 10:30:00',
        'data_final' => '04/12/2019 14:16:00',
        'mensagem' => 'teste',
        'gera_comissao' => 'N',
        'id_su_diagnostico' => '',
        'finaliza_processo' => 'S',
        'status' => 'F',
        'id_tecnico' => '',
    ]));
}
if (!$wants('tables')) {
    exit(0);
}

// Reads.
$page = $recorder->record('list_page', 'POST', $clients, $listing, Requests::query('cliente.id', '>', '0'));
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
// An existing client passes the instance's validation, so it is the template; only identifying fields change.
$client = array_merge(Requests::insertable(Requests::firstRow($page['body'])), [
    'ativo' => 'S',
    'tipo_pessoa' => 'F',
    'razao' => 'Cliente de teste ixc-provedor-client',
    'fantasia' => '',
    'cnpj_cpf' => '529.982.247-25',
    'ie_identidade' => '',
    'endereco' => 'Rua de Teste',
    'numero' => '1',
    'bairro' => 'Centro',
    'cep' => '89999-000',
    'cidade' => $cityId !== '' ? $cityId : '1',
    'telefone_celular' => '(49) 99999-0000',
    'fone' => '',
    'whatsapp' => '',
    'email' => 'cliente.teste@example.com',
    'hotsite_email' => 'cliente.teste@example.com',
    'senha' => '',
    'data_nascimento' => '01/01/1990',
]);

$recorder->record('insert_invalid', 'POST', $clients, $json, (string) json_encode(['ativo' => 'S']));
$id = Requests::firstId($recorder->record('insert', 'POST', $clients, $json, (string) json_encode($client))['body']);
if ($id === '') {
    fwrite(STDERR, "The insert returned no id; skipping the rest. See tests/Fixtures/raw/insert.json.\n");
    exit(1);
}
$one = $clients . '/' . $id;
$found = $recorder->record('find', 'POST', $clients, $listing, Requests::query('cliente.id', '=', $id));
// The row sent back as listed (Y-m-d dates): answers success but blanks the dates.
$asListed = Requests::firstRow($found['body']);
unset($asListed['id']);
$recorder->record('update_as_listed', 'PUT', $one, $json, (string) json_encode($asListed));
$recorder->record('find_after_update_as_listed', 'POST', $clients, $listing, Requests::query('cliente.id', '=', $id));
$recorder->record('update', 'PUT', $one, $json, (string) json_encode(['obs' => 'alterado pelo PUT'] + $client));
$recorder->record('update_invalid', 'PUT', $one, $json, (string) json_encode(['razao' => ''] + $client));
$recorder->record('patch', 'PATCH', $one, $json, (string) json_encode(['obs' => 'alterado pelo PATCH'] + $client));
$recorder->record('patch_partial', 'PATCH', $one, $json, (string) json_encode(['obs' => 'somente obs']));
// Own identifiers, so the uniqueness checks against the client above do not answer first.
$stranger = [
    'cnpj_cpf' => '168.995.350-09',
    'email' => 'outro.teste@example.com',
    'hotsite_email' => 'outro.teste@example.com',
];
$recorder->record(
    'update_not_found',
    'PUT',
    $clients . '/999999999',
    $json,
    (string) json_encode($stranger + $client)
);
$recorder->record('delete', 'DELETE', $one);
$recorder->record('delete_not_found', 'DELETE', $one);
