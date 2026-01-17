<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migration: Adicionar Idempotencia Key em Clientes
 * 
 * Adiciona campo idempotencia_key na tabela clientes para suporte
 * a requisições idempotentes na criação de clientes.
 */
class AddIdempotenciaKeyToClientes extends Migration
{
    public function up()
    {
        $fields = [
            'idempotencia_key' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'after'      => 'documento',
            ],
        ];

        $this->forge->addColumn('clientes', $fields);

        // Índice único para idempotência (permite múltiplos NULL)
        $this->forge->addUniqueKey('idempotencia_key');
    }

    public function down()
    {
        $this->forge->dropColumn('clientes', 'idempotencia_key');
    }
}
