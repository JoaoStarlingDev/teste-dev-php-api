<?php

namespace App\Application\UseCases;

use App\Domain\Auditoria\Auditoria;
use App\Domain\Auditoria\AuditoriaRepositoryInterface;

/**
 * Caso de Uso: Buscar Auditoria
 */
class BuscarAuditoriaUseCase
{
    public function __construct(
        private AuditoriaRepositoryInterface $auditoriaRepository
    ) {
    }

    /**
     * @return Auditoria[]
     */
    public function executar(string $entidadeTipo, ?int $entidadeId = null): array
    {
        return $this->auditoriaRepository->buscarPorEntidade($entidadeTipo, $entidadeId);
    }
}
