<?php

namespace App\Application\Services;

use App\Domain\Cliente\Cliente;
use App\Domain\Cliente\ClienteRepositoryInterface;
use App\Domain\Proposta\ValueObjects\IdempotenciaKey as IdempotenciaKeyVO;

/**
 * Service: Cliente
 * 
 * Responsável por operações relacionadas a clientes.
 */
class ClienteService
{
    public function __construct(
        private ClienteRepositoryInterface $clienteRepository
    ) {
    }

    /**
     * Cria um novo cliente
     * 
     * Se idempotenciaKey for fornecida e cliente já existir com essa chave,
     * retorna o cliente existente (idempotência).
     * 
     * @param string $nome
     * @param string $email
     * @param string|null $documento
     * @param string|null $idempotenciaKey Chave de idempotência (opcional)
     * @return Cliente
     * @throws \InvalidArgumentException Se dados inválidos
     */
    public function criar(
        string $nome,
        string $email,
        ?string $documento = null,
        ?string $idempotenciaKey = null
    ): Cliente {
        // Verifica idempotência primeiro
        if ($idempotenciaKey !== null) {
            $clienteExistente = $this->clienteRepository->buscarPorIdempotenciaKey($idempotenciaKey);
            
            if ($clienteExistente !== null) {
                // Retorna cliente existente (idempotência)
                return $clienteExistente;
            }
        }

        // Verifica se email já existe
        $clienteExistente = $this->clienteRepository->buscarPorEmail($email);
        if ($clienteExistente !== null) {
            throw new \DomainException("Cliente com email '{$email}' já existe");
        }

        // Verifica documento se fornecido
        if ($documento !== null) {
            $clientePorDocumento = $this->clienteRepository->buscarPorDocumento($documento);
            if ($clientePorDocumento !== null) {
                throw new \DomainException("Cliente com documento '{$documento}' já existe");
            }
        }

        // Cria cliente
        $cliente = new Cliente($nome, $email, $documento, $idempotenciaKey);
        $this->clienteRepository->salvar($cliente);

        return $cliente;
    }

    /**
     * Busca cliente por ID
     * 
     * @param int $id
     * @return Cliente|null
     */
    public function buscarPorId(int $id): ?Cliente
    {
        return $this->clienteRepository->buscarPorId($id);
    }

    /**
     * Verifica se cliente existe
     * 
     * @param int $id
     * @return bool
     */
    public function existe(int $id): bool
    {
        return $this->clienteRepository->buscarPorId($id) !== null;
    }
}
