<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Model: Proposta
 * 
 * Gerencia a tabela de propostas com:
 * - Soft delete (deleted_at)
 * - Validação de campos e estados
 * - Timestamps automáticos
 * - Relacionamento com clientes
 * - Controle de versão (optimistic lock)
 * - Idempotência
 */
class PropostaModel extends Model
{
    protected $table            = 'propostas';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'cliente_id',
        'valor',
        'estado',
        'versao',
        'idempotencia_key',
        'enviado_em',
        'respondido_em',
    ];

    // Datas
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    // Validação
    protected $validationRules = [
        'cliente_id' => 'required|integer',
        'valor'      => 'required|decimal|greater_than[0]',
        'estado'     => 'required|in_list[rascunho,enviada,aceita,recusada,cancelada]',
        'versao'     => 'required|integer|greater_than[0]',
        'idempotencia_key' => 'permit_empty|max_length[255]',
    ];

    protected $validationMessages = [
        'cliente_id' => [
            'required' => 'O cliente é obrigatório',
        ],
        'valor' => [
            'required' => 'O valor da proposta é obrigatório',
            'greater_than' => 'O valor deve ser maior que zero',
        ],
        'estado' => [
            'required' => 'O estado da proposta é obrigatório',
            'in_list' => 'O estado informado não é válido',
        ],
    ];

    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert   = ['setVersaoInicial'];
    protected $afterInsert    = [];
    protected $beforeUpdate   = ['incrementarVersao'];
    protected $afterUpdate    = [];
    protected $beforeFind     = [];
    protected $afterFind      = [];
    protected $beforeDelete   = [];
    protected $afterDelete    = [];

    /**
     * Define versão inicial como 1 ao criar proposta
     */
    protected function setVersaoInicial(array $data): array
    {
        if (isset($data['data']) && !isset($data['data']['versao'])) {
            $data['data']['versao'] = 1;
        }
        return $data;
    }

    /**
     * Incrementa versão ao atualizar proposta (optimistic lock)
     */
    protected function incrementarVersao(array $data): array
    {
        if (isset($data['data']['versao'])) {
            $data['data']['versao'] = (int)$data['data']['versao'] + 1;
        }
        return $data;
    }

    /**
     * Busca proposta por ID com verificação de versão
     * 
     * @param int $id
     * @param int|null $versaoEsperada
     * @return array|null
     */
    public function buscarPorIdComVersao(int $id, ?int $versaoEsperada = null): ?array
    {
        $proposta = $this->find($id);

        if ($proposta === null) {
            return null;
        }

        // Verifica versão se informada (optimistic lock)
        if ($versaoEsperada !== null && (int)$proposta['versao'] !== $versaoEsperada) {
            return null; // Versão não corresponde
        }

        return $proposta;
    }

    /**
     * Busca proposta por chave de idempotência
     * 
     * @param string $idempotenciaKey
     * @return array|null
     */
    public function buscarPorIdempotenciaKey(string $idempotenciaKey): ?array
    {
        return $this->where('idempotencia_key', $idempotenciaKey)->first();
    }

    /**
     * Lista propostas com paginação
     * 
     * @param int $offset
     * @param int $limit
     * @return array
     */
    public function listar(int $offset = 0, int $limit = 50): array
    {
        return $this->orderBy('created_at', 'DESC')
                    ->limit($limit, $offset)
                    ->findAll();
    }

    /**
     * Lista propostas por cliente
     * 
     * @param int $clienteId
     * @return array
     */
    public function listarPorCliente(int $clienteId): array
    {
        return $this->where('cliente_id', $clienteId)
                    ->orderBy('created_at', 'DESC')
                    ->findAll();
    }

    /**
     * Lista propostas por estado
     * 
     * @param string $estado
     * @return array
     */
    public function listarPorEstado(string $estado): array
    {
        return $this->where('estado', $estado)
                    ->orderBy('created_at', 'DESC')
                    ->findAll();
    }

    /**
     * Atualiza estado da proposta e define timestamps apropriados
     * 
     * @param int $id
     * @param string $novoEstado
     * @param int $versaoEsperada
     * @return bool
     */
    public function transicionarEstado(int $id, string $novoEstado, int $versaoEsperada): bool
    {
        $proposta = $this->buscarPorIdComVersao($id, $versaoEsperada);

        if ($proposta === null) {
            return false;
        }

        $data = [
            'estado' => $novoEstado,
            'versao' => $versaoEsperada, // Será incrementado no beforeUpdate
        ];

        // Define timestamps baseado no estado
        if ($novoEstado === 'enviada' && empty($proposta['enviado_em'])) {
            $data['enviado_em'] = date('Y-m-d H:i:s');
        }

        if (in_array($novoEstado, ['aceita', 'recusada']) && empty($proposta['respondido_em'])) {
            $data['respondido_em'] = date('Y-m-d H:i:s');
        }

        return $this->update($id, $data) !== false;
    }

    /**
     * Verifica se proposta pode ser editada (apenas RASCUNHO)
     * 
     * @param int $id
     * @return bool
     */
    public function podeSerEditada(int $id): bool
    {
        $proposta = $this->find($id);
        
        if ($proposta === null) {
            return false;
        }

        return $proposta['estado'] === 'rascunho';
    }
}
