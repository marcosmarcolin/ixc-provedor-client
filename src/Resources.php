<?php

declare(strict_types=1);

namespace Marcolin\IxcProvedor;

/**
 * The resources the wiki documents ("Recursos da API"), named after the wiki's titles; each value is the
 * name the API takes. Any other name can still be given as a string to Client::resource().
 *
 * Each comment says how the wiki calls it (list() or post()) and its main parameters.
 */
final class Resources
{
    /** list(['id_contrato' => ...]) */
    public const ALTERAR_STATUS_ACESSO_CONTRATO = 'cliente_contrato_15464';

    /** post(['id_chamado', 'id_setor', 'id_tecnico', 'status' => 'AN', 'mensagem', 'data_inicio', 'data_final']) */
    public const ANALISAR_ORDEM_SERVICO = 'su_oss_chamado_analisar';

    /** list(['qtype' => 'cliente_contrato_ativar_cliente.id', 'id_contrato' => ...]) */
    public const ATIVAR_CONTRATO = 'cliente_contrato_ativar_cliente';

    /** list(['boletos', 'juro', 'multa', 'atualiza_boleto', 'tipo_boleto' => dados|arquivo|mail|sms, 'base64']) */
    public const BOLETO = 'get_boleto';

    /** list(['id' => id do contrato]) */
    public const DESBLOQUEIO_CONFIANCA = 'desbloqueio_confianca';

    /** list(['id' => id do login]) */
    public const DESCONECTAR_LOGIN = 'desconectar_clientes';

    /** post(['id_chamado', 'id_setor', 'id_tecnico', 'id_assunto', 'mensagem', 'status' => 'EN']) */
    public const ENCAMINHAR_ORDEM_SERVICO = 'su_oss_chamado_alterar_setor';

    /** post(['id_chamado', 'data_inicio', 'data_final', 'mensagem', 'finaliza_processo', 'status' => 'F', ...]) */
    public const FINALIZAR_ORDEM_SERVICO = 'su_oss_chamado_fechar';

    /** list() */
    public const GERAR_PROTOCOLO_ATENDIMENTO = 'gerar_protocolo_atendimento';

    /** list(['id' => id da venda, 'base64' => 'S']) */
    public const IMPRIMIR_NOTA = 'imprimir_nota';

    /** post(['id_oss_chamado', 'id_contrato', 'id_patrimonio', 'id_produto', 'qtde_saida', ...]) */
    public const INSERIR_COMODATO_ORDEM_SERVICO = 'su_oss_mov_comodato_wiz_novo';

    /** list(['get_id' => id do contrato]) */
    public const LIBERAR_REDUCAO_VELOCIDADE = 'liberacao_reducao_contrato_29157';

    /** list(['id' => id do contrato]) */
    public const LIBERAR_TEMPORARIAMENTE = 'cliente_contrato_btn_lib_temp_24722';

    /** post(['get_id' => id do login]) */
    public const LIMPAR_MAC = 'radusuarios_25452';

    /** post(['filial_id', 'id_receber', 'id_conta', 'tipo_recebimento', 'data', 'valor_parcela', ...]) */
    public const RECEBER_TITULO = 'fn_areceber_recebimentos_baixas_novo';

    /** post(['id_atendimento', 'data_transferencia', 'id_departamento_origem', 'id_departamento_destino', ...]) */
    public const TRANSFERIR_ATENDIMENTO = 'su_transf_atendimento_26466';

    /** list(['latitude', 'longitude']) or list(['endereco', 'numero', 'bairro', 'cidade', 'estado', 'cep']) */
    public const VERIFICAR_VIABILIDADE = 'viabilidade_tecnica';

    private function __construct()
    {
    }
}
