<?php

namespace App\Domain\Proposta\ValueObjects;

/**
 * Value Object: Cliente
 * 
 * Representa os dados do cliente da proposta.
 */
class Cliente
{
    private string $nome;
    private string $email;
    private ?string $documento;

    public function __construct(string $nome, string $email, ?string $documento = null)
    {
        if (empty(trim($nome))) {
            throw new \InvalidArgumentException('Nome do cliente é obrigatório');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Email inválido');
        }

        $this->nome = trim($nome);
        $this->email = strtolower(trim($email));
        $this->documento = $documento ? trim($documento) : null;
    }

    public function getNome(): string
    {
        return $this->nome;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getDocumento(): ?string
    {
        return $this->documento;
    }
}
