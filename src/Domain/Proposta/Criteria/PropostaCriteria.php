<?php

namespace App\Domain\Proposta\Criteria;

use App\Domain\Proposta\EstadoProposta;

/**
 * Critérios de Busca para Propostas
 * 
 * Encapsula parâmetros de filtro, ordenação e paginação.
 */
class PropostaCriteria
{
    private ?int $clienteId = null;
    private ?EstadoProposta $estado = null;
    private string $ordenarPor = 'created_at';
    private string $direcao = 'DESC';
    private int $pagina = 1;
    private int $porPagina = 50;

    public function __construct(
        ?int $clienteId = null,
        ?EstadoProposta $estado = null,
        string $ordenarPor = 'created_at',
        string $direcao = 'DESC',
        int $pagina = 1,
        int $porPagina = 50
    ) {
        $this->clienteId = $clienteId;
        $this->estado = $estado;
        $this->ordenarPor = $this->validarCampoOrdenacao($ordenarPor);
        $this->direcao = $this->validarDirecao($direcao);
        $this->pagina = max(1, $pagina);
        $this->porPagina = min(max(1, $porPagina), 100); // Máximo 100 por página
    }

    public function getClienteId(): ?int
    {
        return $this->clienteId;
    }

    public function getEstado(): ?EstadoProposta
    {
        return $this->estado;
    }

    public function getOrdenarPor(): string
    {
        return $this->ordenarPor;
    }

    public function getDirecao(): string
    {
        return $this->direcao;
    }

    public function getPagina(): int
    {
        return $this->pagina;
    }

    public function getPorPagina(): int
    {
        return $this->porPagina;
    }

    public function getOffset(): int
    {
        return ($this->pagina - 1) * $this->porPagina;
    }

    public function temFiltros(): bool
    {
        return $this->clienteId !== null || $this->estado !== null;
    }

    private function validarCampoOrdenacao(string $campo): string
    {
        $camposValidos = ['created_at', 'updated_at', 'valor', 'estado', 'id'];
        
        if (!in_array($campo, $camposValidos, true)) {
            return 'created_at';
        }

        return $campo;
    }

    private function validarDirecao(string $direcao): string
    {
        $direcao = strtoupper($direcao);
        
        return in_array($direcao, ['ASC', 'DESC'], true) ? $direcao : 'DESC';
    }
}
