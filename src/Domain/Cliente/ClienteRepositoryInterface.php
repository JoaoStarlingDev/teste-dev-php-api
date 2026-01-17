<?php

namespace App\Domain\Cliente;

/**
 * Interface do Repositório de Clientes
 */
interface ClienteRepositoryInterface
{
    public function salvar(Cliente $cliente): void;
    public function buscarPorId(int $id): ?Cliente;
    public function buscarPorEmail(string $email): ?Cliente;
    public function buscarPorDocumento(string $documento): ?Cliente;
    public function buscarPorIdempotenciaKey(string $idempotenciaKey): ?Cliente;
}
