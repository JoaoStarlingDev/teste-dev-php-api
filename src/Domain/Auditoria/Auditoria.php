<?php

namespace App\Domain\Auditoria;

use DateTimeImmutable;

/**
 * Entidade de Domínio: Auditoria
 * 
 * Registra todas as alterações e ações realizadas no sistema.
 * 
 * IMPORTANTE:
 * - Dados são armazenados como arrays (não JSON)
 * - Serialização JSON deve ser feita na camada de Infrastructure (Repository/Model)
 * - Eventos são tipados via EventoAuditoria enum
 * - Domain não conhece detalhes de serialização
 */
class Auditoria
{
    private ?int $id = null;
    private string $entidadeTipo;
    private ?int $entidadeId;
    private EventoAuditoria $evento;
    private array $dadosAnteriores; // Array, não JSON
    private array $dadosNovos; // Array, não JSON
    private ?string $usuario = null;
    private ?string $ipOrigem = null;
    private DateTimeImmutable $ocorridoEm;

    public function __construct(
        string $entidadeTipo,
        ?int $entidadeId,
        EventoAuditoria $evento,
        array $dadosAnteriores = [],
        array $dadosNovos = [],
        ?string $usuario = null,
        ?string $ipOrigem = null
    ) {
        $this->entidadeTipo = $entidadeTipo;
        $this->entidadeId = $entidadeId;
        $this->evento = $evento;
        $this->dadosAnteriores = $dadosAnteriores;
        $this->dadosNovos = $dadosNovos;
        $this->usuario = $usuario;
        $this->ipOrigem = $ipOrigem;
        $this->ocorridoEm = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getEntidadeTipo(): string
    {
        return $this->entidadeTipo;
    }

    public function getEntidadeId(): ?int
    {
        return $this->entidadeId;
    }

    public function getEvento(): EventoAuditoria
    {
        return $this->evento;
    }

    /**
     * Retorna dados anteriores (array)
     */
    public function getDadosAnteriores(): array
    {
        return $this->dadosAnteriores;
    }

    /**
     * Retorna dados novos (array)
     */
    public function getDadosNovos(): array
    {
        return $this->dadosNovos;
    }

    public function getUsuario(): ?string
    {
        return $this->usuario;
    }

    public function getIpOrigem(): ?string
    {
        return $this->ipOrigem;
    }

    public function getOcorridoEm(): DateTimeImmutable
    {
        return $this->ocorridoEm;
    }

    /**
     * @deprecated Use getEvento() ao invés
     */
    public function getAcao(): string
    {
        return $this->evento->value;
    }
}
