<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migration: Criar Tabela Clientes
 * 
 * Cria a tabela de clientes com:
 * - Campos básicos (nome, email, documento)
 * - Soft delete (deleted_at)
 * - Timestamps (created_at, updated_at)
 * - Índices para performance (email único, deleted_at, documento)
 */
class CreateClientesTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'nome' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => false,
            ],
            'email' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => false,
            ],
            'documento' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => true,
            ],
            'deleted_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'TIMESTAMP',
                'null' => false,
                'default' => 'CURRENT_TIMESTAMP',
            ],
            'updated_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
        ]);

        // Chave primária
        $this->forge->addKey('id', true);

        // Índice único para email (permite múltiplos NULL em soft delete)
        $this->forge->addUniqueKey('email');

        // Índices para performance
        $this->forge->addKey('deleted_at');
        $this->forge->addKey('documento');

        // Criar tabela
        $this->forge->createTable('clientes', true);
    }

    public function down()
    {
        $this->forge->dropTable('clientes', true);
    }
}
