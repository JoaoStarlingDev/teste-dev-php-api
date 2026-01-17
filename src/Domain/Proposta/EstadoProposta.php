<?php

namespace App\Domain\Proposta;

/**
 * Enum de Estados da Proposta
 * 
 * Define os estados possíveis da proposta seguindo uma máquina de estados (FSM).
 * Estados finais são imutáveis e não permitem transições.
 * 
 * Estados:
 * - RASCUNHO: Estado inicial, permite edição e transições
 * - ENVIADA: Estado intermediário, aguarda resposta
 * - ACEITA: Estado final, imutável
 * - RECUSADA: Estado final, imutável
 * - CANCELADA: Estado final, imutável
 */
enum EstadoProposta: string
{
    case RASCUNHO = 'rascunho';
    case ENVIADA = 'enviada';
    case ACEITA = 'aceita';
    case RECUSADA = 'recusada';
    case CANCELADA = 'cancelada';

    /**
     * Retorna todos os estados possíveis
     * 
     * @return array<EstadoProposta>
     */
    public static function todos(): array
    {
        return self::cases();
    }

    /**
     * Retorna apenas estados finais
     * 
     * @return array<EstadoProposta>
     */
    public static function estadosFinais(): array
    {
        return [self::ACEITA, self::RECUSADA, self::CANCELADA];
    }

    /**
     * Retorna apenas estados não finais (inicial e intermediários)
     * 
     * @return array<EstadoProposta>
     */
    public static function estadosNaoFinais(): array
    {
        return [self::RASCUNHO, self::ENVIADA];
    }

    /**
     * Verifica se é um estado final (imutável)
     * 
     * Estados finais não permitem:
     * - Transições para outros estados
     * - Edição de dados
     * - Cancelamento
     * 
     * @return bool
     */
    public function isFinal(): bool
    {
        return in_array($this, self::estadosFinais(), true);
    }

    /**
     * Verifica se é um estado inicial
     * 
     * @return bool
     */
    public function isInicial(): bool
    {
        return $this === self::RASCUNHO;
    }

    /**
     * Verifica se é um estado intermediário
     * 
     * @return bool
     */
    public function isIntermediario(): bool
    {
        return $this === self::ENVIADA;
    }

    /**
     * Verifica se o estado permite edição de dados
     * 
     * Apenas RASCUNHO permite edição.
     * Estados finais e ENVIADA não permitem edição.
     * 
     * @return bool
     */
    public function permiteEdicao(): bool
    {
        return $this === self::RASCUNHO;
    }

    /**
     * Verifica se pode transicionar para o estado informado
     * 
     * Regras:
     * - RASCUNHO pode transicionar para: ENVIADA, CANCELADA
     * - ENVIADA pode transicionar para: ACEITA, RECUSADA, CANCELADA
     * - Estados finais não podem transicionar (retorna false)
     * 
     * @param EstadoProposta $novoEstado
     * @return bool
     */
    public function podeTransicionarPara(EstadoProposta $novoEstado): bool
    {
        // Estados finais são imutáveis
        if ($this->isFinal()) {
            return false;
        }

        // Não pode transicionar para o mesmo estado
        if ($this === $novoEstado) {
            return false;
        }

        return match ($this) {
            self::RASCUNHO => in_array($novoEstado, [self::ENVIADA, self::CANCELADA], true),
            self::ENVIADA => in_array($novoEstado, [self::ACEITA, self::RECUSADA, self::CANCELADA], true),
            default => false,
        };
    }

    /**
     * Retorna os estados válidos para transição a partir do estado atual
     * 
     * @return array<EstadoProposta>
     */
    public function estadosValidosParaTransicao(): array
    {
        if ($this->isFinal()) {
            return [];
        }

        return match ($this) {
            self::RASCUNHO => [self::ENVIADA, self::CANCELADA],
            self::ENVIADA => [self::ACEITA, self::RECUSADA, self::CANCELADA],
            default => [],
        };
    }

    /**
     * Retorna descrição legível do estado
     * 
     * @return string
     */
    public function descricao(): string
    {
        return match ($this) {
            self::RASCUNHO => 'Rascunho - Permite edição e pode ser enviada ou cancelada',
            self::ENVIADA => 'Enviada - Aguardando resposta do cliente',
            self::ACEITA => 'Aceita - Proposta aceita pelo cliente (estado final)',
            self::RECUSADA => 'Recusada - Proposta recusada pelo cliente (estado final)',
            self::CANCELADA => 'Cancelada - Proposta cancelada (estado final)',
        };
    }

    /**
     * Verifica se o estado requer timestamp específico
     * 
     * @return string|null Nome do campo de timestamp ou null
     */
    public function campoTimestampRequerido(): ?string
    {
        return match ($this) {
            self::ENVIADA => 'enviado_em',
            self::ACEITA, self::RECUSADA => 'respondido_em',
            default => null,
        };
    }
}
