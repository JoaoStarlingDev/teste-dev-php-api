<?php

namespace App\Domain\Cliente;

use DateTimeImmutable;

/**
 * Entidade de Domínio: Cliente
 * 
 * Representa um cliente no sistema.
 */
class Cliente
{
    private ?int $id = null;
    private string $nome;
    private string $email;
    private ?string $documento;
    private ?string $idempotenciaKey = null;
    private DateTimeImmutable $criadoEm;
    private ?DateTimeImmutable $atualizadoEm = null;

    public function __construct(
        string $nome,
        string $email,
        ?string $documento = null,
        ?string $idempotenciaKey = null
    ) {
        $this->validarDados($nome, $email);
        
        $this->nome = trim($nome);
        $this->email = strtolower(trim($email));
        $this->documento = $documento ? trim($documento) : null;
        $this->idempotenciaKey = $idempotenciaKey ? trim($idempotenciaKey) : null;
        $this->criadoEm = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
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

    public function getCriadoEm(): DateTimeImmutable
    {
        return $this->criadoEm;
    }

    public function getAtualizadoEm(): ?DateTimeImmutable
    {
        return $this->atualizadoEm;
    }

    public function getIdempotenciaKey(): ?string
    {
        return $this->idempotenciaKey;
    }

    public function marcarComoAtualizado(): void
    {
        $this->atualizadoEm = new DateTimeImmutable();
    }

    private function validarDados(string $nome, string $email): void
    {
        if (empty(trim($nome))) {
            throw new \InvalidArgumentException('Nome do cliente é obrigatório');
        }

        if (strlen(trim($nome)) < 3) {
            throw new \InvalidArgumentException('Nome deve ter no mínimo 3 caracteres');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Email inválido');
        }
    }
}
