<?php

namespace App\Infrastructure\Repository;

use App\Domain\Cliente\Cliente;
use App\Domain\Cliente\ClienteRepositoryInterface;

/**
 * Implementação do Repositório de Clientes
 * 
 * Implementação em memória para demonstração.
 * Em produção, substituir por implementação com banco de dados.
 */
class ClienteRepository implements ClienteRepositoryInterface
{
    private array $clientes = [];
    private array $emailIndex = [];
    private array $documentoIndex = [];
    private array $idempotenciaIndex = [];
    private int $nextId = 1;

    public function salvar(Cliente $cliente): void
    {
        if ($cliente->getId() === null) {
            $cliente->setId($this->nextId++);
        }

        $id = $cliente->getId();
        $this->clientes[$id] = $cliente;

        // Indexa por email
        $email = $cliente->getEmail();
        $this->emailIndex[$email] = $id;

        // Indexa por documento se fornecido
        if ($cliente->getDocumento() !== null) {
            $documento = $cliente->getDocumento();
            $this->documentoIndex[$documento] = $id;
        }

        // Indexa por chave de idempotência se fornecido
        if ($cliente->getIdempotenciaKey() !== null) {
            $key = $cliente->getIdempotenciaKey();
            $this->idempotenciaIndex[$key] = $id;
        }
    }

    public function buscarPorId(int $id): ?Cliente
    {
        return $this->clientes[$id] ?? null;
    }

    public function buscarPorEmail(string $email): ?Cliente
    {
        $email = strtolower(trim($email));
        
        if (!isset($this->emailIndex[$email])) {
            return null;
        }

        $id = $this->emailIndex[$email];
        return $this->clientes[$id] ?? null;
    }

    public function buscarPorDocumento(string $documento): ?Cliente
    {
        $documento = trim($documento);
        
        if (!isset($this->documentoIndex[$documento])) {
            return null;
        }

        $id = $this->documentoIndex[$documento];
        return $this->clientes[$id] ?? null;
    }

    public function buscarPorIdempotenciaKey(string $idempotenciaKey): ?Cliente
    {
        $key = trim($idempotenciaKey);
        
        if (!isset($this->idempotenciaIndex[$key])) {
            return null;
        }

        $id = $this->idempotenciaIndex[$key];
        return $this->clientes[$id] ?? null;
    }
}
