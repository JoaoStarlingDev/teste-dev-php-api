<?php

namespace App\Presentation\Controllers\Api\V1;

use App\Application\Services\ClienteService;
use App\Presentation\Http\ResponseFormatter;
use App\Presentation\Http\ExceptionHandler;
use App\Presentation\Http\HttpRequestInterface;

/**
 * Controller REST: Cliente (API v1)
 * 
 * Endpoints:
 * - POST /api/v1/clientes - Criar cliente
 * - GET /api/v1/clientes/{id} - Buscar cliente
 */
class ClienteController
{
    public function __construct(
        private ClienteService $clienteService,
        private HttpRequestInterface $request
    ) {
    }

    /**
     * POST /api/v1/clientes
     * 
     * Cria um novo cliente.
     * 
     * Headers:
     * - Idempotency-Key (opcional): Chave de idempotência
     * 
     * Body:
     * {
     *   "nome": "João Silva",
     *   "email": "joao@example.com",
     *   "documento": "123.456.789-00"
     * }
     */
    public function criar(array $request): array
    {
        try {
            // Extrai Idempotency-Key do header (se disponível)
            $idempotencyKey = $this->extrairIdempotencyKey($request);

            // Valida dados obrigatórios
            $this->validarCriacaoCliente($request);

            // Cria cliente
            $cliente = $this->clienteService->criar(
                $request['nome'],
                $request['email'],
                $request['documento'] ?? null,
                $idempotencyKey
            );

            // Retorna resposta de sucesso
            return ResponseFormatter::success(
                $this->serializarCliente($cliente),
                201,
                'Cliente criado com sucesso'
            );
        } catch (\Throwable $e) {
            return ExceptionHandler::handle($e)[0];
        }
    }

    /**
     * GET /api/v1/clientes/{id}
     * 
     * Busca cliente por ID.
     */
    public function buscar(int $id): array
    {
        try {
            $cliente = $this->clienteService->buscarPorId($id);

            if ($cliente === null) {
                return ResponseFormatter::notFound('Cliente');
            }

            return ResponseFormatter::success($this->serializarCliente($cliente));
        } catch (\Throwable $e) {
            return ExceptionHandler::handle($e)[0];
        }
    }

    /**
     * Valida dados de criação de cliente
     */
    private function validarCriacaoCliente(array $request): void
    {
        if (empty($request['nome'])) {
            throw new \InvalidArgumentException('Nome é obrigatório');
        }

        if (empty($request['email'])) {
            throw new \InvalidArgumentException('Email é obrigatório');
        }
        
        // Validação de formato básica (validação completa no Domain)
    }

    /**
     * Extrai Idempotency-Key do header ou request
     */
    private function extrairIdempotencyKey(array $request): ?string
    {
        // Busca no header HTTP via abstração
        $headerKey = $this->request->getHeader('Idempotency-Key');
        if ($headerKey !== null) {
            return $headerKey;
        }

        // Verifica no body (fallback)
        return $request['idempotency_key'] ?? null;
    }

    /**
     * Serializa cliente para resposta JSON
     */
    private function serializarCliente($cliente): array
    {
        return [
            'id' => $cliente->getId(),
            'nome' => $cliente->getNome(),
            'email' => $cliente->getEmail(),
            'documento' => $cliente->getDocumento(),
            'idempotencia_key' => $cliente->getIdempotenciaKey(),
            'created_at' => $cliente->getCriadoEm()->format('Y-m-d H:i:s'),
            'updated_at' => $cliente->getAtualizadoEm()?->format('Y-m-d H:i:s'),
        ];
    }
}
