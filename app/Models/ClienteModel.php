<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Model: Cliente
 * 
 * Gerencia a tabela de clientes com:
 * - Soft delete (deleted_at)
 * - Validação de campos
 * - Timestamps automáticos
 * - Proteção contra campos não permitidos
 */
class ClienteModel extends Model
{
    protected $table            = 'clientes';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'nome',
        'email',
        'documento',
    ];

    // Datas
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    // Validação
    protected $validationRules = [
        'nome'    => 'required|min_length[3]|max_length[255]',
        'email'   => 'required|valid_email|max_length[255]',
        'documento' => 'permit_empty|max_length[20]',
    ];

    protected $validationMessages = [
        'nome' => [
            'required' => 'O nome do cliente é obrigatório',
            'min_length' => 'O nome deve ter no mínimo 3 caracteres',
        ],
        'email' => [
            'required' => 'O email do cliente é obrigatório',
            'valid_email' => 'O email informado não é válido',
        ],
    ];

    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert   = [];
    protected $afterInsert    = [];
    protected $beforeUpdate   = [];
    protected $afterUpdate    = [];
    protected $beforeFind     = [];
    protected $afterFind      = [];
    protected $beforeDelete   = [];
    protected $afterDelete    = [];

    /**
     * Busca cliente por email (considerando soft delete)
     * 
     * @param string $email
     * @return array|null
     */
    public function buscarPorEmail(string $email): ?array
    {
        return $this->where('email', $email)->first();
    }

    /**
     * Busca cliente por documento (considerando soft delete)
     * 
     * @param string $documento
     * @return array|null
     */
    public function buscarPorDocumento(string $documento): ?array
    {
        return $this->where('documento', $documento)->first();
    }

    /**
     * Verifica se cliente existe e não está deletado
     * 
     * @param int $id
     * @return bool
     */
    public function existe(int $id): bool
    {
        return $this->find($id) !== null;
    }
}
