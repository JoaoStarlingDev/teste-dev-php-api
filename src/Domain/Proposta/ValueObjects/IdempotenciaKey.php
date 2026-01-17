<?php

namespace App\Domain\Proposta\ValueObjects;

/**
 * Value Object: Idempotência Key
 * 
 * Garante que operações idênticas não sejam executadas múltiplas vezes.
 */
class IdempotenciaKey
{
    private string $key;

    public function __construct(string $key)
    {
        if (empty(trim($key))) {
            throw new \InvalidArgumentException('Chave de idempotência não pode ser vazia');
        }

        if (strlen($key) > 255) {
            throw new \InvalidArgumentException('Chave de idempotência muito longa');
        }

        $this->key = trim($key);
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function equals(IdempotenciaKey $outro): bool
    {
        return $this->key === $outro->key;
    }

    public function __toString(): string
    {
        return $this->key;
    }
}
