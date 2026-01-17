<?php

namespace App\Domain\Auditoria;

/**
 * Enum: Evento de Auditoria
 * 
 * Define os tipos de eventos que podem ser registrados na auditoria.
 * Cada evento representa uma ação específica sobre uma entidade.
 */
enum EventoAuditoria: string
{
    /**
     * Entidade criada
     * 
     * Evento disparado quando uma nova entidade é criada no sistema.
     * Payload: dados completos da entidade criada.
     */
    case CREATED = 'CREATED';

    /**
     * Campos atualizados
     * 
     * Evento disparado quando campos de uma entidade são atualizados
     * (exceto mudança de estado).
     * Payload: campos anteriores e novos (diff).
     */
    case UPDATED_FIELDS = 'UPDATED_FIELDS';

    /**
     * Status/Estado alterado
     * 
     * Evento disparado quando o estado/status de uma entidade muda.
     * Payload: estado anterior e novo, dados completos antes e depois.
     */
    case STATUS_CHANGED = 'STATUS_CHANGED';

    /**
     * Exclusão lógica
     * 
     * Evento disparado quando uma entidade é marcada como deletada
     * (soft delete).
     * Payload: dados completos antes da exclusão.
     */
    case DELETED_LOGICAL = 'DELETED_LOGICAL';

    /**
     * Retorna descrição do evento
     */
    public function getDescricao(): string
    {
        return match ($this) {
            self::CREATED => 'Entidade criada',
            self::UPDATED_FIELDS => 'Campos atualizados',
            self::STATUS_CHANGED => 'Status/Estado alterado',
            self::DELETED_LOGICAL => 'Exclusão lógica realizada',
        };
    }

    /**
     * Verifica se o evento requer dados anteriores
     */
    public function requerDadosAnteriores(): bool
    {
        return match ($this) {
            self::CREATED => false,
            self::UPDATED_FIELDS => true,
            self::STATUS_CHANGED => true,
            self::DELETED_LOGICAL => true,
        };
    }

    /**
     * Verifica se o evento requer dados novos
     */
    public function requerDadosNovos(): bool
    {
        return match ($this) {
            self::CREATED => true,
            self::UPDATED_FIELDS => true,
            self::STATUS_CHANGED => true,
            self::DELETED_LOGICAL => false,
        };
    }
}
