<?php

namespace App\Application\UseCases;

use App\Domain\Proposta\Proposta;
use App\Domain\Proposta\PropostaRepositoryInterface;

/**
 * Caso de Uso: Buscar Proposta
 */
class BuscarPropostaUseCase
{
    public function __construct(
        private PropostaRepositoryInterface $propostaRepository
    ) {
    }

    public function executar(int $id): ?Proposta
    {
        return $this->propostaRepository->buscarPorId($id);
    }
}
