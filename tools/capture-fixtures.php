<?php

/**
 * Captures real responses from the IXC Provedor instance configured in .env as fixtures.
 *
 * Usage (Docker only): docker compose run --rm php composer capture [-- routes|resources|patch|delete_many|dates]
 * Opt-in groups, each with the API user's group restricted by hand first, then restored:
 * blocked_ip (networks that leave the capture's IP out) and blocked_time (hours that leave now out).
 * Raw responses go to tests/Fixtures/raw/ (gitignored); anonymized ones to tests/Fixtures/.
 * The write scenarios create, change and delete a client: point it at a disposable instance only.
 */

declare(strict_types=1);

use Marcolin\IxcProvedor\RowEncoder;
use Marcolin\IxcProvedor\Tools\Anonymizer;
use Marcolin\IxcProvedor\Tools\FixtureRecorder;
use Marcolin\IxcProvedor\Tools\HttpCapture;
use Marcolin\IxcProvedor\Tools\Requests;
use Marcolin\IxcProvedor\Tools\TestData;

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

$http = new HttpCapture($setting('IXC_HOST'), $setting('IXC_TOKEN'), $setting('IXC_INSECURE') === '1');
$recorder = new FixtureRecorder(
    $http,
    new Anonymizer(),
    $root . '/tests/Fixtures',
    STDOUT
);
$userId = explode(':', $setting('IXC_TOKEN'), 2)[0];
$fakeSecret = str_repeat('0', 64);
$json = ['Content-Type' => 'application/json'];
$listing = ['ixcsoft' => 'listar', 'Content-Type' => 'application/json'];
$clients = 'webservice/v1/cliente';

$groups = array_slice($argv ?? [], 1);
$wants = static function (string $group) use ($groups): bool {
    return $groups === [] || in_array($group, $groups, true);
};

// Access refused by the user's group rules: only run when asked, since the restriction is set by hand.
if (in_array('blocked_ip', $groups, true)) {
    $recorder->record('error_blocked_ip', 'POST', $clients, $listing, Requests::query('cliente.id', '>', '0'));
}
if (in_array('blocked_time', $groups, true)) {
    $recorder->record('error_blocked_time', 'POST', $clients, $listing, Requests::query('cliente.id', '>', '0'));
}

// PATCH answers in a different format per route, so a few routes are tried with an id that does not exist.
if ($wants('patch')) {
    $patch = (string) json_encode(['obs' => 'teste']);
    $recorder->record('patch_not_found', 'PATCH', $clients . '/999999999', $json, $patch);
    $recorder->record('patch_not_found_contract', 'PATCH', 'webservice/v1/cliente_contrato/999999999', $json, $patch);
    $recorder->record('patch_server_error', 'PATCH', 'webservice/v1/cidade/999999999', $json, $patch);
    $recorder->record('patch_not_implemented', 'PATCH', 'webservice/v1/cliente_arquivos/999999999', $json, $patch);
}

// Dates on routes other than cliente: a new contract and invoice of the test client, written back as listed
// (Y-m-d) and as RowEncoder sends them (d/m/Y), each followed by a read.
if ($wants('dates')) {
    $data = new TestData($http);
    $owner = $data->client();
    $contract = $data->contract($owner);
    // The listing gives tipo_recebimento empty, which the write refuses, so it goes again as inserted.
    $records = [
        'contract' => ['cliente_contrato', $contract, []],
        'invoice' => ['fn_areceber', $data->invoice($owner, $contract), ['tipo_recebimento' => 'B']],
    ];
    foreach ($records as $label => [$route, $recordId, $extra]) {
        $path = 'webservice/v1/' . $route;
        $read = Requests::query($route . '.id', '=', $recordId);
        $listed = $recorder->record('find_' . $label, 'POST', $path, $listing, $read)['body'];
        $row = array_merge(Requests::firstRow($listed), $extra);
        unset($row['id']);
        $one = $path . '/' . $recordId;
        $recorder->record('update_as_listed_' . $label, 'PUT', $one, $json, (string) json_encode($row));
        $recorder->record('update_encoded_' . $label, 'PUT', $one, $json, (new RowEncoder())->encode($row));
        $recorder->record('find_after_update_encoded_' . $label, 'POST', $path, $listing, $read);
    }
}

// Several ids in one DELETE, on files of the test client created here.
if ($wants('delete_many')) {
    $files = 'webservice/v1/cliente_arquivos';
    $owner = (new TestData($http))->client();
    $upload = $root . '/tests/Fixtures/raw/upload.txt';
    file_put_contents($upload, "conteudo de teste do ixc-provedor-client\n");
    $fileIds = [];
    foreach (['um', 'dois', 'tres'] as $label) {
        $fileIds[] = Requests::firstId($http->send('POST', $files, [], [
            'id_cliente' => $owner,
            'descricao' => 'Arquivo ' . $label,
            'local_arquivo' => new CURLFile($upload, 'text/plain', $label . '.txt'),
        ])['body']);
    }
    $recorder->record('delete_many', 'DELETE', $files . '/' . $fileIds[0] . ',' . $fileIds[1]);
    $recorder->record('delete_many_with_missing', 'DELETE', $files . '/' . $fileIds[2] . ',999999999');
    $range = Requests::query('cliente_arquivos.id', '>=', $fileIds[0], [
        'grid_param' => (string) json_encode([['TB' => 'cliente_arquivos.id', 'OP' => '<=', 'P' => $fileIds[2]]]),
    ]);
    $recorder->record('find_deleted_many', 'POST', $files, $listing, $range);
    $recorder->record('delete_not_found_error', 'DELETE', $files . '/999999999');
}

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
// Resources with real records, created through the API (see TestData). Forwarding an order needs the
// API user linked to a collaborator who belongs to the order's sector; the scenario records the error otherwise.
if ($wants('resources')) {
    $data = new TestData($http);
    /**
     * @param array<string, string> $parameters
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    $named = static function (
        string $fixture,
        string $resource,
        string $style,
        array $parameters
    ) use ($recorder): array {
        $headers = $style === 'listar'
            ? ['ixcsoft' => 'listar', 'Content-Type' => 'application/json']
            : ['Content-Type' => 'application/json'];
        $body = (string) json_encode($parameters);

        return $recorder->record($fixture, 'POST', 'webservice/v1/' . $resource, $headers, $body);
    };
    $now = date('d/m/Y H:i:s');
    $client = $data->client();
    $contract = $data->contract($client);
    $named('resource_activate_contract', 'cliente_contrato_ativar_cliente', 'listar', [
        'qtype' => 'cliente_contrato_ativar_cliente.id',
        'id_contrato' => $contract,
    ]);
    $named('resource_contract_status_active', 'cliente_contrato_15464', 'listar', ['id_contrato' => $contract]);
    $named('resource_trust_unlock_active', 'desbloqueio_confianca', 'listar', ['id' => $contract]);
    $named('resource_temporary_release', 'cliente_contrato_btn_lib_temp_24722', 'listar', ['id' => $contract]);
    $named('resource_speed_release', 'liberacao_reducao_contrato_29157', 'listar', ['get_id' => $contract]);

    $invoice = $data->invoice($client, $contract);
    $boleto = ['boletos' => $invoice, 'juro' => 'N', 'multa' => 'N', 'atualiza_boleto' => 'N'];
    $named('resource_boleto_data', 'get_boleto', 'listar', $boleto + ['tipo_boleto' => 'dados']);
    $named('resource_boleto_base64', 'get_boleto', 'listar', $boleto + ['tipo_boleto' => 'arquivo', 'base64' => 'S']);
    $named('resource_boleto_binary', 'get_boleto', 'listar', $boleto + ['tipo_boleto' => 'arquivo', 'base64' => 'N']);
    $named('resource_boleto_mail', 'get_boleto', 'listar', $boleto + ['tipo_boleto' => 'mail']);
    $named('resource_boleto_sms', 'get_boleto', 'listar', $boleto + ['tipo_boleto' => 'sms']);
    $named('resource_print_invoice_missing', 'imprimir_nota', 'listar', ['id' => '999999999', 'base64' => 'S']);
    $named('resource_viability', 'viabilidade_tecnica', 'listar', ['latitude' => '-27.100', 'longitude' => '-52.615']);

    $login = $data->login($client, $contract);
    $named('resource_disconnect_login', 'desconectar_clientes', 'listar', ['id' => $login]);
    $named('resource_clear_mac', 'radusuarios_25452', 'post', ['get_id' => $login]);

    $order = $data->order($client, $contract, $login);
    $named('resource_analyze_order', 'su_oss_chamado_analisar', 'post', [
        'id_setor' => '1',
        'id_chamado' => $order,
        'status' => 'AN',
        'id_tecnico' => '1',
        'mensagem' => 'analisando',
        'data_inicio' => $now,
        'data_final' => $now,
    ]);
    $named('resource_forward_order', 'su_oss_chamado_alterar_setor', 'post', [
        'id_chamado' => $order,
        'id_setor' => '2',
        'id_tecnico' => '1',
        'id_assunto' => '1',
        'mensagem' => 'encaminhando',
        'status' => 'EN',
    ]);
    $named('resource_comodato_incomplete', 'su_oss_mov_comodato_wiz_novo', 'post', [
        'id_oss_chamado' => $order,
        'id_contrato' => $contract,
        'id_produto' => '1',
        'descricao' => 'teste',
        'data' => date('d/m/Y'),
        'qtde_saida' => '1',
    ]);
    $named('resource_close_order', 'su_oss_chamado_fechar', 'post', [
        'id_chamado' => $order,
        'data_inicio' => $now,
        'data_final' => $now,
        'mensagem' => 'finalizado',
        'gera_comissao' => 'N',
        'id_su_diagnostico' => '',
        'finaliza_processo' => 'S',
        'status' => 'F',
        'id_tecnico' => '1',
    ]);

    $protocol = $http->send('POST', 'webservice/v1/gerar_protocolo_atendimento', $listing, '{}')['body'];
    $protocol = json_decode($protocol);
    $ticket = $data->ticket($client, $contract, $login, is_string($protocol) ? $protocol : '');
    $named('resource_transfer_ticket', 'su_transf_atendimento_26466', 'post', [
        'id_atendimento' => $ticket,
        'data_transferencia' => $now,
        'id_departamento_origem' => '1',
        'id_departamento_destino' => '2',
        'id_responsavel_tecnico' => '2',
    ]);
    $named('resource_receive_invoice', 'fn_areceber_recebimentos_baixas_novo', 'post', [
        'filial_id' => '1',
        'id_receber' => $invoice,
        'conta_' => '1',
        'id_conta' => '1',
        'tipo_recebimento' => 'D',
        'data' => date('d/m/Y'),
        'valor_parcela' => 'R$99,90',
        'credito' => '99,90',
        'debito' => '99,90',
        'valor_total_recebido' => 'R$99,90',
        'historico' => 'teste',
        'previsao' => 'N',
        'tipo_lanc' => 'R',
    ]);

    // What the API lets go; the finalized order, received invoice and contract stay.
    $data->delete('su_ticket', $ticket);
    $data->delete('radusuarios', $login);
}
if (!$wants('routes')) {
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
// A file goes as multipart form data; the record is listed back to show where it was stored.
$upload = $root . '/tests/Fixtures/raw/upload.txt';
file_put_contents($upload, "conteudo de teste do ixc-provedor-client\n");
$files = 'webservice/v1/cliente_arquivos';
$uploaded = $recorder->record('insert_with_file', 'POST', $files, [], [
    'id_cliente' => $id,
    'descricao' => 'Contrato de adesão',
    'local_arquivo' => new CURLFile($upload, 'text/plain', 'contrato.txt'),
]);
$fileId = Requests::firstId($uploaded['body']);
$recorder->record('find_file', 'POST', $files, $listing, Requests::query('cliente_arquivos.id', '=', $fileId));
$recorder->record('delete_file', 'DELETE', $files . '/' . $fileId);

$recorder->record('delete', 'DELETE', $one);
$recorder->record('delete_not_found', 'DELETE', $one);
