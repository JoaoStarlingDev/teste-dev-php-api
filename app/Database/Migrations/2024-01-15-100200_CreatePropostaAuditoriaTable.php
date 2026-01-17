<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migration: Criar Tabela Proposta Auditoria
 * 
 * Cria a tabela de auditoria de propostas com:
 * - Relacionamento com propostas (FK)
 * - Campos de auditoria (acao, estados, dados JSON)
 * - Metadados (usuario, ip_origem)
 * - Timestamp de ocorrência (created_at)
 * - Índices para consultas históricas e filtros
 * 
 * IMPORTANTE: Tabela imutável - registros nunca são atualizados ou deletados
 */
class CreatePropostaAuditoriaTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'constraint'     => 20,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'proposta_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],
            'acao' => [
                'type'       => 'ENUM',
                'constraint' => ['CRIAR', 'ATUALIZAR', 'TRANSIÇÃO_ESTADO', 'CANCELAR'],
                'null'       => false,
            ],
            'estado_anterior' => [
                'type'       => 'ENUM',
                'constraint' => ['rascunho', 'enviada', 'aceita', 'recusada', 'cancelada'],
                'null'       => true,
            ],
            'estado_novo' => [
                'type'       => 'ENUM',
                'constraint' => ['rascunho', 'enviada', 'aceita', 'recusada', 'cancelada'],
                'null'       => true,
            ],
            'dados_anteriores' => [
                'type' => 'JSON',
                'null' => true,
            ],
            'dados_novos' => [
                'type' => 'JSON',
                'null' => true,
            ],
            'usuario' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
            'ip_origem' => [
                'type'       => 'VARCHAR',
                'constraint' => 45, // Suporta IPv4 e IPv6
                'null'       => true,
            ],
            'created_at' => [
                'type' => 'TIMESTAMP',
                'null' => false,
                'default' => 'CURRENT_TIMESTAMP',
            ],
        ]);

        // Chave primária
        $this->forge->addKey('id', true);

        // Chave estrangeira para propostas (RESTRICT para manter integridade)
        $this->forge->addForeignKey('proposta_id', 'propostas', 'id', 'RESTRICT', 'RESTRICT');

        // Índices para performance
        $this->forge->addKey('proposta_id');
        $this->forge->addKey('created_at');
        $this->forge->addKey('acao');

        // Índice composto para consultas históricas (proposta + data)
        $this->forge->addKey(['proposta_id', 'created_at']);

        // Criar tabela
        $this->forge->createTable('proposta_auditoria', true);
    }

    public function down()
    {
        $this->forge->dropTable('proposta_auditoria', true);
    }
}
