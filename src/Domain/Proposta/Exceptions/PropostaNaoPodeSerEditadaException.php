<?php

namespace App\Domain\Proposta\Exceptions;

use App\Domain\Proposta\EstadoProposta;

/**
 * Exceção: Proposta Não Pode Ser Editada
 * 
 * Lançada quando uma tentativa de edição é feita em uma proposta
 * que não está no estado RASCUNHO.
 * 
 * Regra: Apenas propostas em RASCUNHO podem ser editadas.
 * 
 * Estados que não permitem edição:
 * - ENVIADA
 * - ACEITA (final)
 * - RECUSADA (final)
 * - CANCELADA (final)
 */
class PropostaNaoPodeSerEditadaException extends \DomainException
{
    private EstadoProposta $estado;

    public function __construct(EstadoProposta $estado, string $message = "", int $code = 0, ?\Throwable $previous = null)
    {
        $this->estado = $estado;

        if (empty($message)) {
            $message = sprintf(
                "Proposta não pode ser editada no estado '%s'. Apenas propostas em 'rascunho' podem ser editadas.",
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
