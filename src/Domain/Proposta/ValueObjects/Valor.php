<?php

namespace App\Domain\Proposta\ValueObjects;

/**
 * Value Object: Valor
 * 
 * Representa o valor monetário da proposta com validação.
 */
class Valor
{
    private float $valor;

    public function __construct(float $valor)
    {
        if ($valor < 0) {
            throw new \InvalidArgumentException('Valor não pode ser negativo');
        }

        if ($valor === 0.0) {
            throw new \InvalidArgumentException('Valor deve ser maior que zero');
        }

        $this->valor = round($valor, 2);
    }

    public function getValor(): float
    {
        return $this->valor;
    }

    public function equals(Valor $outro): bool
    {
        return abs($this->valor - $outro->valor) < 0.01;
    }

    public function __toString(): string
    {
        return number_format($this->valor, 2, ',', '.');
    }
}
