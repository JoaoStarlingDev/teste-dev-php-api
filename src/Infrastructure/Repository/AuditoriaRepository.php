<?php

namespace App\Infrastructure\Repository;

use App\Domain\Auditoria\Auditoria;
use App\Domain\Auditoria\AuditoriaRepositoryInterface;

/**
 * Implementação do Repositório de Auditoria
 * 
 * Implementação em memória para demonstração.
 * Em produção, substituir por implementação com banco de dados.
 */
class AuditoriaRepository implements AuditoriaRepositoryInterface
{
    private array $auditorias = [];
    private int $nextId = 1;

    public function registrar(Auditoria $auditoria): void
    {
        if ($auditoria->getId() === null) {
            $auditoria->setId($this->nextId++);
        }

        $this->auditorias[] = $auditoria;
    }

    public function buscarPorEntidade(string $entidadeTipo, ?int $entidadeId = null): array
    {
        return array_filter(
            $this->auditorias,
            function (Auditoria $auditoria) use ($entidadeTipo, $entidadeId) {
                $matchTipo = $auditoria->getEntidadeTipo() === $entidadeTipo;
                $matchId = $entidadeId === null || $auditoria->getEntidadeId() === $entidadeId;
                return $matchTipo && $matchId;
            }
        );
    }
}
