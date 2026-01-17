<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Model: Proposta Auditoria
 * 
 * Gerencia a tabela de auditoria de propostas com:
 * - Tabela imutável (sem soft delete, sem updates)
 * - Validação de campos e ações
 * - Timestamps automáticos
 * - Relacionamento com propostas
 * - Armazenamento de dados JSON
 * 
 * IMPORTANTE: Registros nunca são atualizados ou deletados
 */
class PropostaAuditoriaModel extends Model
{
    protected $table            = 'proposta_auditoria';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false; // Tabela imutável - sem soft delete
    protected $protectFields    = true;
    protected $allowedFields    = [
        'proposta_id',
        'acao',
        'estado_anterior',
        'estado_novo',
        'dados_anteriores',
        'dados_novos',
        'usuario',
        'ip_origem',
    ];

    // Datas
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = null; // Sem updated_at (tabela imutável)
    protected $deletedField  = null; // Sem deleted_at (tabela imutável)

    // Validação
    protected $validationRules = [
        'proposta_id' => 'required|integer',
        'acao'        => 'required|in_list[CRIAR,ATUALIZAR,TRANSIÇÃO_ESTADO,CANCELAR]',
        'estado_anterior' => 'permit_empty|in_list[rascunho,enviada,aceita,recusada,cancelada]',
        'estado_novo'     => 'permit_empty|in_list[rascunho,enviada,aceita,recusada,cancelada]',
        'dados_anteriores' => 'permit_empty',
        'dados_novos'      => 'permit_empty',
        'usuario'          => 'permit_empty|max_length[100]',
        'ip_origem'        => 'permit_empty|max_length[45]',
    ];

    protected $validationMessages = [
        'proposta_id' => [
            'required' => 'A proposta é obrigatória',
        ],
        'acao' => [
            'required' => 'A ação é obrigatória',
            'in_list' => 'A ação informada não é válida',
        ],
    ];

    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert   = ['validarDadosJSON'];
    protected $afterInsert    = [];
    protected $beforeUpdate   = []; // Desabilitado - tabela imutável
    protected $afterUpdate    = [];
    protected $beforeFind     = [];
    protected $afterFind      = [];
    protected $beforeDelete   = []; // Desabilitado - tabela imutável
    protected $afterDelete    = [];

    /**
     * Valida e converte dados para JSON antes de inserir
     */
    protected function validarDadosJSON(array $data): array
    {
        // Converte arrays para JSON se necessário
        if (isset($data['data']['dados_anteriores']) && is_array($data['data']['dados_anteriores'])) {
            $data['data']['dados_anteriores'] = json_encode($data['data']['dados_anteriores']);
        }

        if (isset($data['data']['dados_novos']) && is_array($data['data']['dados_novos'])) {
            $data['data']['dados_novos'] = json_encode($data['data']['dados_novos']);
        }

        return $data;
    }

    /**
     * Busca auditoria por proposta
     * 
     * @param int $propostaId
     * @return array
     */
    public function buscarPorProposta(int $propostaId): array
    {
        return $this->where('proposta_id', $propostaId)
                    ->orderBy('created_at', 'ASC')
                    ->findAll();
    }

    /**
     * Busca auditoria por ação
     * 
     * @param string $acao
     * @return array
     */
    public function buscarPorAcao(string $acao): array
    {
        return $this->where('acao', $acao)
                    ->orderBy('created_at', 'DESC')
                    ->findAll();
    }

    /**
     * Busca auditoria por proposta e ação
     * 
     * @param int $propostaId
     * @param string $acao
     * @return array
     */
    public function buscarPorPropostaEAcao(int $propostaId, string $acao): array
    {
        return $this->where('proposta_id', $propostaId)
                    ->where('acao', $acao)
                    ->orderBy('created_at', 'DESC')
                    ->findAll();
    }

    /**
     * Busca auditoria por período
     * 
     * @param string $dataInicio
     * @param string $dataFim
     * @return array
     */
    public function buscarPorPeriodo(string $dataInicio, string $dataFim): array
    {
        return $this->where('created_at >=', $dataInicio)
                    ->where('created_at <=', $dataFim)
                    ->orderBy('created_at', 'DESC')
                    ->findAll();
    }

    /**
     * Registra auditoria de criação de proposta
     * 
     * @param int $propostaId
     * @param array $dadosNovos
     * @param string|null $usuario
     * @param string|null $ipOrigem
     * @return int|false ID do registro criado ou false em caso de erro
     */
    public function registrarCriacao(int $propostaId, array $dadosNovos, ?string $usuario = null, ?string $ipOrigem = null)
    {
        return $this->insert([
            'proposta_id'      => $propostaId,
            'acao'            => 'CRIAR',
            'estado_anterior' => null,
            'estado_novo'     => $dadosNovos['estado'] ?? null,
            'dados_anteriores' => [],
            'dados_novos'     => $dadosNovos,
            'usuario'         => $usuario,
            'ip_origem'       => $ipOrigem,
        ]);
    }

    /**
     * Registra auditoria de atualização de proposta
     * 
     * @param int $propostaId
     * @param array $dadosAnteriores
     * @param array $dadosNovos
     * @param string|null $usuario
     * @param string|null $ipOrigem
     * @return int|false
     */
    public function registrarAtualizacao(int $propostaId, array $dadosAnteriores, array $dadosNovos, ?string $usuario = null, ?string $ipOrigem = null)
    {
        return $this->insert([
            'proposta_id'      => $propostaId,
            'acao'            => 'ATUALIZAR',
            'estado_anterior' => $dadosAnteriores['estado'] ?? null,
            'estado_novo'     => $dadosNovos['estado'] ?? null,
            'dados_anteriores' => $dadosAnteriores,
            'dados_novos'     => $dadosNovos,
            'usuario'         => $usuario,
            'ip_origem'       => $ipOrigem,
        ]);
    }

    /**
     * Registra auditoria de transição de estado
     * 
     * @param int $propostaId
     * @param string $estadoAnterior
     * @param string $estadoNovo
     * @param array $dadosAnteriores
     * @param array $dadosNovos
     * @param string|null $usuario
     * @param string|null $ipOrigem
     * @return int|false
     */
    public function registrarTransicaoEstado(int $propostaId, string $estadoAnterior, string $estadoNovo, array $dadosAnteriores, array $dadosNovos, ?string $usuario = null, ?string $ipOrigem = null)
    {
        return $this->insert([
            'proposta_id'      => $propostaId,
            'acao'            => 'TRANSIÇÃO_ESTADO',
            'estado_anterior' => $estadoAnterior,
            'estado_novo'     => $estadoNovo,
            'dados_anteriores' => $dadosAnteriores,
            'dados_novos'     => $dadosNovos,
            'usuario'         => $usuario,
            'ip_origem'       => $ipOrigem,
        ]);
    }
}
