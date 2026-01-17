<?php

namespace App\Domain\Proposta\Exceptions;

use App\Domain\Proposta\EstadoProposta;

/**
 * Exceção: Estado Final Imutável
 * 
 * Lançada quando uma operação tenta modificar uma proposta
 * que está em um estado final (ACEITA, RECUSADA, CANCELADA).
 * 
 * Estados finais são imutáveis e não permitem:
 * - Transições para outros estados
 * - Edição de dados
 * - Cancelamento
 * - Qualquer alteração
 */
class EstadoFinalImutavelException extends \DomainException
{
    private EstadoProposta $estado;

    public function __construct(EstadoProposta $estado, string $message = "", int $code = 0, ?\Throwable $previous = null)
    {
        $this->estado = $estado;

        if (empty($message)) {
            $message = sprintf(
                "Estado final '%s' é imutável. Não é possível realizar alterações em propostas com estados finais.",
                $estado->value
            );
        }

        parent::__construct($message, $code, $previous);
    }

    /**
     * Retorna o estado que causou a exceção
     * 
     * @return EstadoProposta
     */
    public function getEstado(): EstadoProposta
    {
        return $this->estado;
    }

    /**
     * Retorna o valor do estado como string
     * 
     * @return string
     */
    public function getEstadoValue(): string
    {
        return $this->estado->value;
    }
}
