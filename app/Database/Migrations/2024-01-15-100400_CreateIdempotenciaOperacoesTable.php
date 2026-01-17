<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migration: Criar Tabela de Idempotência de Operações
 * 
 * Armazena resultados de operações idempotentes para permitir
 * retornar respostas anteriores em caso de requisições duplicadas.
 * 
 * Usado para:
 * - Submit de propostas
 * - Outras operações que precisam de idempotência
 */
class CreateIdempotenciaOperacoesTable extends Migration
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
            'idempotencia_key' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => false,
            ],
            'tipo_operacao' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => false,
                'comment'    => 'Ex: submeter_proposta, criar_cliente, etc',
            ],
            'entidade_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
                'comment'    => 'ID da entidade afetada (proposta_id, cliente_id, etc)',
            ],
            'resultado' => [
                'type' => 'JSON',
                'null' => true,
                'comment' => 'Snapshot do resultado da operação',
            ],
            'created_at' => [
                'type' => 'TIMESTAMP',
                'null' => false,
                'default' => 'CURRENT_TIMESTAMP',
            ],
        ]);

        // Chave primária
        $this->forge->addKey('id', true);

        // Índice único: idempotency_key + tipo_operacao
        $this->forge->addUniqueKey(['idempotencia_key', 'tipo_operacao']);

        // Índices para performance
        $this->forge->addKey('idempotencia_key');
        $this->forge->addKey('tipo_operacao');
        $this->forge->addKey('entidade_id');
        $this->forge->addKey('created_at');

        // Criar tabela
        $this->forge->createTable('idempotencia_operacoes', true);
    }

    public function down()
    {
        $this->forge->dropTable('idempotencia_operacoes', true);
    }
}
