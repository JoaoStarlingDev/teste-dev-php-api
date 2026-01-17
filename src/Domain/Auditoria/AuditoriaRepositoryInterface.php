<?php

namespace App\Domain\Auditoria;

/**
 * Interface do Repositório de Auditoria
 */
interface AuditoriaRepositoryInterface
{
    public function registrar(Auditoria $auditoria): void;
    public function buscarPorEntidade(string $entidadeTipo, ?int $entidadeId = null): array;
}
