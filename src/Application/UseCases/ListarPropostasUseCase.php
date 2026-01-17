<?php

namespace App\Application\UseCases;

use App\Domain\Proposta\Proposta;
use App\Domain\Proposta\PropostaRepositoryInterface;

/**
 * Caso de Uso: Listar Propostas
 */
class ListarPropostasUseCase
{
    public function __construct(
        private PropostaRepositoryInterface $propostaRepository
    ) {
    }

    /**
     * @return Proposta[]
     */
    public function executar(int $pagina = 1, int $porPagina = 50): array
    {
        $offset = ($pagina - 1) * $porPagina;
        return $this->propostaRepository->buscarTodos($offset, $porPagina);
    }
}
