<?php

namespace App\Domain\Proposta\Exceptions;

use App\Domain\Proposta\EstadoProposta;

/**
 * Exceção: Transição de Estado Inválida
 * 
 * Lançada quando uma transição de estado não é permitida
 * pela máquina de estados (FSM).
 * 
 * Transições válidas:
 * - RASCUNHO → ENVIADA
 * - RASCUNHO → CANCELADA
 * - ENVIADA → ACEITA
 * - ENVIADA → RECUSADA
 * - ENVIADA → CANCELADA
 * 
 * Todas as outras transições são inválidas.
 */
class TransicaoEstadoInvalidaException extends \DomainException
{
    private EstadoProposta $estadoAtual;
    private EstadoProposta $estadoDesejado;

    public function __construct(
        EstadoProposta $estadoAtual,
        EstadoProposta $estadoDesejado,
        string $message = "",
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        $this->estadoAtual = $estadoAtual;
        $this->estadoDesejado = $estadoDesejado;

        if (empty($message)) {
            $estadosValidos = $estadoAtual->estadosValidosParaTransicao();
            $estadosValidosStr = array_map(fn($e) => $e->value, $estadosValidos);

            $message = sprintf(
                "Transição inválida de '%s' para '%s'. Estados válidos a partir de '%s': %s",
                $estadoAtual->value,
                $estadoDesejado->value,
                $estadoAtual->value,
                implode(', ', $estadosValidosStr)
            );
        }

        parent::__construct($message, $code, $previous);
    }

    /**
     * Retorna o estado atual
     * 
     * @return EstadoProposta
     */
    public function getEstadoAtual(): EstadoProposta
    {
        return $this->estadoAtual;
    }

    /**
     * Retorna o estado desejado
     * 
     * @return EstadoProposta
     */
    public function getEstadoDesejado(): EstadoProposta
    {
        return $this->estadoDesejado;
    }

    /**
     * Retorna o valor do estado atual como string
     * 
     * @return string
     */
    public function getEstadoAtualValue(): string
    {
        return $this->estadoAtual->value;
    }

    /**
     * Retorna o valor do estado desejado como string
     * 
     * @return string
     */
    public function getEstadoDesejadoValue(): string
    {
        return $this->estadoDesejado->value;
    }
}
