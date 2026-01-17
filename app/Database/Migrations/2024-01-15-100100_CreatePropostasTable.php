<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migration: Criar Tabela Propostas
 * 
 * Cria a tabela de propostas com:
 * - Relacionamento com clientes (FK)
 * - Campos de negócio (valor, estado, versao)
 * - Idempotência (idempotencia_key único)
 * - Soft delete (deleted_at)
 * - Timestamps e campos de controle (enviado_em, respondido_em)
 * - Índices para performance e consultas frequentes
 */
class CreatePropostasTable extends Migration
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
            'cliente_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],
            'valor' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
                'null'       => false,
            ],
            'estado' => [
                'type'       => 'ENUM',
                'constraint' => ['rascunho', 'enviada', 'aceita', 'recusada', 'cancelada'],
                'default'    => 'rascunho',
                'null'       => false,
            ],
            'versao' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'default'    => 1,
                'null'       => false,
            ],
            'idempotencia_key' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'enviado_em' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
            'respondido_em' => [
                'type' => 'TIMESTAMP',
                'null' => true,
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

        // Chave estrangeira para clientes
        $this->forge->addForeignKey('cliente_id', 'clientes', 'id', 'RESTRICT', 'RESTRICT');

        // Índice único para idempotência (permite múltiplos NULL)
        $this->forge->addUniqueKey('idempotencia_key');

        // Índices para performance
        $this->forge->addKey('cliente_id');
        $this->forge->addKey('estado');
        $this->forge->addKey('deleted_at');
        $this->forge->addKey('created_at');

        // Índice composto para consultas frequentes (cliente + estado)
        $this->forge->addKey(['cliente_id', 'estado']);

        // Criar tabela
        $this->forge->createTable('propostas', true);
    }

    public function down()
    {
        $this->forge->dropTable('propostas', true);
    }
}
