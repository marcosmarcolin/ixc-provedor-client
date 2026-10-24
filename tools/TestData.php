<?php

declare(strict_types=1);

namespace Marcolin\IxcProvedor\Tools;

use RuntimeException;

/**
 * Creates, through the API, the records the resource scenarios need: a client (reused across runs), and
 * per run a contract, an invoice, a login, a service order and a ticket.
 *
 * The bodies follow the wiki's templates plus what this instance's validation asked for. Some records
 * cannot be deleted afterwards (the API refuses to delete a finalized order, a received invoice, a
 * contract with invoices or a client with links), so run it only against a disposable instance.
 */
final class TestData
{
    public const CLIENT_NAME = 'Cliente de teste dos recursos';

    private HttpCapture $http;

    /** @var array<string, string> */
    private array $listing = ['ixcsoft' => 'listar', 'Content-Type' => 'application/json'];

    /** @var array<string, string> */
    private array $json = ['Content-Type' => 'application/json'];

    public function __construct(HttpCapture $http)
    {
        $this->http = $http;
    }

    /**
     * The test client, created from an existing one the first time.
     */
    public function client(): string
    {
        $found = $this->list('cliente', 'cliente.razao', '=', self::CLIENT_NAME);
        if ($found !== '') {
            return $found;
        }
        $template = Requests::insertable($this->firstRow('cliente', 'cliente.id', '>', '0'));

        return $this->insert('cliente', array_merge($template, [
            'razao' => self::CLIENT_NAME,
            'cnpj_cpf' => '529.982.247-25',
            'email' => 'recursos@example.com',
            'hotsite_email' => 'recursos@example.com',
            'telefone_celular' => '(49) 99999-0000',
            'data_nascimento' => '01/01/1990',
        ]));
    }

    /**
     * A new contract, still to be activated, with the first plan, type, model, wallet and seller.
     */
    public function contract(string $client): string
    {
        return $this->insert('cliente_contrato', [
            'tipo' => 'I',
            'id_cliente' => $client,
            'id_vd_contrato' => $this->firstId('vd_contratos'),
            'contrato' => 'Contrato de teste',
            'id_tipo_contrato' => $this->firstId('cliente_contrato_tipo'),
            'id_modelo' => $this->firstId('cliente_contrato_modelo'),
            'id_filial' => $this->firstId('filial'),
            'data' => date('d/m/Y'),
            'id_tipo_documento' => $this->firstId('tipo_documento'),
            'id_carteira_cobranca' => $this->firstId('fn_carteira_cobranca'),
            'id_vendedor' => $this->firstId('vendedor'),
            'motivo_inclusao' => 'I',
            'id_motivo_inclusao' => $this->firstId('cliente_contrato_motivo_inclusao'),
            'cc_previsao' => 'P',
            'tipo_cobranca' => 'P',
            'renovacao_automatica' => 'S',
            'base_geracao_tipo_doc' => 'P',
            'bloqueio_automatico' => 'S',
            'aviso_atraso' => 'S',
            'endereco_padrao_cliente' => 'S',
            'status' => 'P',
            'status_internet' => 'D',
            'tipo_localidade' => 'U',
        ]);
    }

    public function invoice(string $client, string $contract): string
    {
        return $this->insert('fn_areceber', [
            'id_cliente' => $client,
            'id_contrato' => $contract,
            'id_conta' => $this->firstId('contas'),
            'filial_id' => $this->firstId('filial'),
            'data_emissao' => date('d/m/Y'),
            'data_vencimento' => date('d/m/Y', (int) strtotime('+10 days')),
            'valor' => '99.90',
            'previsao' => 'N',
            'tipo_recebimento' => 'B',
            'id_carteira_cobranca' => $this->firstId('fn_carteira_cobranca'),
            'status' => 'A',
            'liberado' => 'S',
            'forma_recebimento' => 'M',
            'documento' => 'TESTE',
        ]);
    }

    public function login(string $client, string $contract): string
    {
        return $this->insert('radusuarios', [
            'autenticacao' => 'L',
            'tipo_conexao_mapa' => 'F',
            'id_cliente' => $client,
            'id_contrato' => $contract,
            'login' => 'teste.api.' . date('His'),
            'senha' => 'teste123',
            'senha_md5' => 'N',
            'ativo' => 'S',
            'login_simultaneo' => '1',
            'auto_preencher_ip' => 'H',
            'fixar_ip' => 'H',
            'relacionar_ip_ao_login' => 'H',
            'autenticacao_por_mac' => 'P',
            'auto_preencher_mac' => 'H',
            'relacionar_mac_ao_login' => 'H',
            'tipo_vinculo_plano' => 'D',
            'id_grupo' => $this->firstId('radgrupos'),
            'endereco_padrao_cliente' => 'S',
        ]);
    }

    public function order(string $client, string $contract, string $login): string
    {
        return $this->insert('su_oss_chamado', [
            'tipo' => 'C',
            'id_assunto' => $this->firstId('su_oss_assunto'),
            'setor' => $this->firstId('empresa_setor'),
            'id_cliente' => $client,
            'id_contrato_kit' => $contract,
            'id_login' => $login,
            'origem_endereco' => 'L',
            'prioridade' => 'N',
            'status' => 'A',
            'id_filial' => $this->firstId('filial'),
            'mensagem' => 'OS de teste',
        ]);
    }

    public function ticket(string $client, string $contract, string $login, string $protocol): string
    {
        return $this->insert('su_ticket', [
            'tipo' => 'C',
            'protocolo' => $protocol,
            'id_cliente' => $client,
            'id_contrato' => $contract,
            'id_login' => $login,
            'id_assunto' => $this->firstId('su_oss_assunto'),
            'titulo' => 'Internet lenta',
            'origem_endereco' => 'L',
            'id_ticket_setor' => '1',
            'prioridade' => 'M',
            'id_ticket_origem' => 'I',
            'menssagem' => 'Teste',
            'interacao_pendente' => 'N',
            'su_status' => 'N',
            'status' => 'T',
            'atualizar_cliente' => 'S',
            'atualizar_login' => 'S',
        ]);
    }

    /**
     * Deletes what the API lets go; the rest stays on the instance.
     */
    public function delete(string $table, string $id): void
    {
        $this->http->send('DELETE', 'webservice/v1/' . $table . '/' . $id);
    }

    /**
     * @param array<string, string> $row
     */
    private function insert(string $table, array $row): string
    {
        $answer = $this->http->send('POST', 'webservice/v1/' . $table, $this->json, (string) json_encode($row));
        $id = Requests::firstId($answer['body']);
        if ($id === '') {
            throw new RuntimeException(sprintf('Creating the test %s failed: %s', $table, $answer['body']));
        }

        return $id;
    }

    private function firstId(string $table): string
    {
        $id = $this->list($table, $table . '.id', '>', '0');
        if ($id === '') {
            throw new RuntimeException(sprintf('The instance has no %s, which the test data needs.', $table));
        }

        return $id;
    }

    private function list(string $table, string $field, string $operator, string $value): string
    {
        $answer = $this->http->send(
            'POST',
            'webservice/v1/' . $table,
            $this->listing,
            Requests::query($field, $operator, $value)
        );

        return Requests::firstId($answer['body']);
    }

    /**
     * @return array<string, mixed>
     */
    private function firstRow(string $table, string $field, string $operator, string $value): array
    {
        $answer = $this->http->send(
            'POST',
            'webservice/v1/' . $table,
            $this->listing,
            Requests::query($field, $operator, $value)
        );

        return Requests::firstRow($answer['body']);
    }
}
