<?php

namespace App\Domain\Idempotencia;

use DateTimeImmutable;

/**
 * Entidade de Domínio: Idempotência de Operação
 * 
 * Armazena resultado de operações idempotentes para permitir
 * retornar respostas anteriores em caso de requisições duplicadas.
 */
class IdempotenciaOperacao
{
    private ?int $id = null;
    private string $idempotenciaKey;
    private string $tipoOperacao;
    private int $entidadeId;
    private ?array $resultado = null;
    private DateTimeImmutable $createdAt;

    public function __construct(
        string $idempotenciaKey,
        string $tipoOperacao,
        int $entidadeId,
        ?array $resultado = null
    ) {
        if (empty(trim($idempotenciaKey))) {
            throw new \InvalidArgumentException('Chave de idempotência não pode ser vazia');
        }

        if (empty(trim($tipoOperacao))) {
            throw new \InvalidArgumentException('Tipo de operação não pode ser vazio');
        }

        $this->idempotenciaKey = trim($idempotenciaKey);
        $this->tipoOperacao = trim($tipoOperacao);
        $this->entidadeId = $entidadeId;
        $this->resultado = $resultado;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getIdempotenciaKey(): string
    {
        return $this->idempotenciaKey;
    }

    public function getTipoOperacao(): string
    {
        return $this->tipoOperacao;
    }

    public function getEntidadeId(): int
    {
        return $this->entidadeId;
    }

    public function getResultado(): ?array
    {
        return $this->resultado;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
