<?php

namespace App\Presentation\Controllers\Api\V1;

use App\Application\Services\PropostaService;
use App\Domain\Proposta\EstadoProposta;
use App\Domain\Proposta\Criteria\PropostaCriteria;
use App\Presentation\Http\ResponseFormatter;
use App\Presentation\Http\ExceptionHandler;
use App\Presentation\Http\HttpRequestInterface;

/**
 * Controller REST: Proposta (API v1)
 * 
 * Endpoints:
 * - POST /api/v1/propostas - Criar proposta
 * - GET /api/v1/propostas - Listar propostas
 * - GET /api/v1/propostas/{id} - Buscar proposta
 * - POST /api/v1/propostas/{id}/submeter - Submeter proposta
 * - POST /api/v1/propostas/{id}/aprovar - Aprovar proposta
 * - POST /api/v1/propostas/{id}/rejeitar - Rejeitar proposta
 * - POST /api/v1/propostas/{id}/cancelar - Cancelar proposta
 */
class PropostaController
{
    public function __construct(
        private PropostaService $propostaService,
        private HttpRequestInterface $request
    ) {
    }

    /**
     * POST /api/v1/propostas
     * 
     * Cria uma nova proposta em estado RASCUNHO (DRAFT).
     * 
     * Headers:
     * - Idempotency-Key (opcional): Chave de idempotência
     * 
     * Body:
     * {
     *   "cliente_id": 1,
     *   "valor": 1500.00,
     *   "usuario": "admin"
     * }
     */
    public function criar(array $request): array
    {
        try {
            // Extrai Idempotency-Key
            $idempotencyKey = $this->extrairIdempotencyKey($request);

            // Valida dados obrigatórios
            $this->validarCriacaoProposta($request);

            // Cria proposta
            $proposta = $this->propostaService->criarProposta(
                (int) $request['cliente_id'],
                (float) $request['valor'],
                $idempotencyKey,
                $request['usuario'] ?? null,
                $this->request->getIpOrigem()
            );

            // Determina status code (201 se novo, 200 se idempotente)
            $statusCode = $idempotencyKey !== null && $proposta->getVersao() === 1 ? 201 : 200;

            return ResponseFormatter::success(
                $this->serializarProposta($proposta),
                $statusCode,
                'Proposta criada com sucesso'
            );
        } catch (\Throwable $e) {
            return ExceptionHandler::handle($e)[0];
        }
    }

    /**
     * GET /api/v1/propostas
     * 
     * Lista propostas com filtros, ordenação e paginação obrigatória.
     * 
     * Query params:
     * - pagina (obrigatório, mínimo: 1)
     * - por_pagina (obrigatório, mínimo: 1, máximo: 100)
     * - cliente_id (opcional): Filtrar por cliente
     * - estado (opcional): Filtrar por estado (rascunho, enviada, aceita, recusada, cancelada)
     * - ordenar_por (opcional, default: created_at): Campo para ordenação (id, valor, estado, created_at, updated_at)
     * - direcao (opcional, default: DESC): Direção da ordenação (ASC, DESC)
     */
    public function listar(array $queryParams = []): array
    {
        try {
            // Valida paginação obrigatória
            if (empty($queryParams['pagina']) || !is_numeric($queryParams['pagina'])) {
                throw new \InvalidArgumentException('Parâmetro "pagina" é obrigatório e deve ser um número');
            }

            if (empty($queryParams['por_pagina']) || !is_numeric($queryParams['por_pagina'])) {
                throw new \InvalidArgumentException('Parâmetro "por_pagina" é obrigatório e deve ser um número');
            }

            $pagina = (int) $queryParams['pagina'];
            $porPagina = (int) $queryParams['por_pagina'];

            if ($pagina < 1) {
                throw new \InvalidArgumentException('Parâmetro "pagina" deve ser maior ou igual a 1');
            }

            if ($porPagina < 1 || $porPagina > 100) {
                throw new \InvalidArgumentException('Parâmetro "por_pagina" deve estar entre 1 e 100');
            }

            // Processa filtros
            $clienteId = !empty($queryParams['cliente_id']) ? (int) $queryParams['cliente_id'] : null;
            $estado = null;
            if (!empty($queryParams['estado'])) {
                try {
                    $estado = EstadoProposta::from($queryParams['estado']);
                } catch (\ValueError $e) {
                    throw new \InvalidArgumentException('Estado inválido. Valores válidos: rascunho, enviada, aceita, recusada, cancelada');
                }
            }

            // Processa ordenação
            $ordenarPor = $queryParams['ordenar_por'] ?? 'created_at';
            $direcao = strtoupper($queryParams['direcao'] ?? 'DESC');

            // Cria criteria
            $criteria = new PropostaCriteria(
                $clienteId,
                $estado,
                $ordenarPor,
                $direcao,
                $pagina,
                $porPagina
            );

            // Busca propostas
            [$propostas, $total] = $this->propostaService->listarComCriteria($criteria);

            // Serializa propostas
            $dados = array_map(
                fn($proposta) => $this->serializarProposta($proposta),
                $propostas
            );

            // Retorna resposta paginada
            return ResponseFormatter::paginated($dados, $pagina, $porPagina, $total);
        } catch (\Throwable $e) {
            return ExceptionHandler::handle($e)[0];
        }
    }

    /**
     * GET /api/v1/propostas/{id}
     * 
     * Busca proposta por ID.
     */
    public function buscar(int $id): array
    {
        try {
            $proposta = $this->propostaService->buscarPorId($id);

            if ($proposta === null) {
                return ResponseFormatter::notFound('Proposta');
            }

            return ResponseFormatter::success($this->serializarProposta($proposta));
        } catch (\Throwable $e) {
            return ExceptionHandler::handle($e)[0];
        }
    }

    /**
     * PATCH /api/v1/propostas/{id}
     * 
     * Atualiza campos de uma proposta (exceto estado).
     * Apenas propostas em RASCUNHO podem ser editadas.
     * 
     * Body:
     * {
     *   "valor": 2000.00,
     *   "versao": 1,
     *   "usuario": "admin"
     * }
     */
    public function atualizar(int $id, array $request): array
    {
        try {
            // Valida versão obrigatória
            if (empty($request['versao'])) {
                throw new \InvalidArgumentException('Versão é obrigatória');
            }

            // Atualiza proposta
            $proposta = $this->propostaService->atualizarProposta(
                $id,
                (int) $request['versao'],
                isset($request['valor']) ? (float) $request['valor'] : null,
                $request['usuario'] ?? null,
                $this->request->getIpOrigem()
            );

            return ResponseFormatter::success(
                $this->serializarProposta($proposta),
                200,
                'Proposta atualizada com sucesso'
            );
        } catch (\Throwable $e) {
            return ExceptionHandler::handle($e)[0];
        }
    }

    /**
     * POST /api/v1/propostas/{id}/submeter
     * 
     * Submete uma proposta (RASCUNHO → ENVIADA).
     * 
     * Headers:
     * - Idempotency-Key (opcional): Chave de idempotência
     * 
     * Body:
     * {
     *   "versao": 1,
     *   "usuario": "admin"
     * }
     */
    public function submeter(int $id, array $request): array
    {
        try {
            // Valida versão obrigatória
            if (empty($request['versao'])) {
                throw new \InvalidArgumentException('Versão é obrigatória');
            }

            // Extrai Idempotency-Key
            $idempotencyKey = $this->extrairIdempotencyKey($request);

            // Submete proposta
            $proposta = $this->propostaService->submeterProposta(
                $id,
                (int) $request['versao'],
                $idempotencyKey,
                $request['usuario'] ?? null,
                $this->request->getIpOrigem()
            );

            return ResponseFormatter::success(
                $this->serializarProposta($proposta),
                200,
                'Proposta submetida com sucesso'
            );
        } catch (\Throwable $e) {
            return ExceptionHandler::handle($e)[0];
        }
    }

    /**
     * POST /api/v1/propostas/{id}/aprovar
     * 
     * Aprova uma proposta (ENVIADA → ACEITA).
     * 
     * Body:
     * {
     *   "versao": 2,
     *   "usuario": "cliente"
     * }
     */
    public function aprovar(int $id, array $request): array
    {
        try {
            // Valida versão obrigatória
            if (empty($request['versao'])) {
                throw new \InvalidArgumentException('Versão é obrigatória');
            }

            // Aprova proposta
            $proposta = $this->propostaService->aprovarProposta(
                $id,
                (int) $request['versao'],
                $request['usuario'] ?? null,
                $this->request->getIpOrigem()
            );

            return ResponseFormatter::success(
                $this->serializarProposta($proposta),
                200,
                'Proposta aprovada com sucesso'
            );
        } catch (\Throwable $e) {
            return ExceptionHandler::handle($e)[0];
        }
    }

    /**
     * POST /api/v1/propostas/{id}/rejeitar
     * 
     * Rejeita uma proposta (ENVIADA → RECUSADA).
     * 
     * Body:
     * {
     *   "versao": 2,
     *   "usuario": "cliente"
     * }
     */
    public function rejeitar(int $id, array $request): array
    {
        try {
            // Valida versão obrigatória
            if (empty($request['versao'])) {
                throw new \InvalidArgumentException('Versão é obrigatória');
            }

            // Rejeita proposta
            $proposta = $this->propostaService->rejeitarProposta(
                $id,
                (int) $request['versao'],
                $request['usuario'] ?? null,
                $this->request->getIpOrigem()
            );

            return ResponseFormatter::success(
                $this->serializarProposta($proposta),
                200,
                'Proposta rejeitada com sucesso'
            );
        } catch (\Throwable $e) {
            return ExceptionHandler::handle($e)[0];
        }
    }

    /**
     * POST /api/v1/propostas/{id}/cancelar
     * 
     * Cancela uma proposta (RASCUNHO/ENVIADA → CANCELADA).
     * 
     * Body:
     * {
     *   "versao": 1,
     *   "usuario": "admin"
     * }
     */
    public function cancelar(int $id, array $request): array
    {
        try {
            // Valida versão obrigatória
            if (empty($request['versao'])) {
                throw new \InvalidArgumentException('Versão é obrigatória');
            }

            // Cancela proposta
            $proposta = $this->propostaService->cancelarProposta(
                $id,
                (int) $request['versao'],
                $request['usuario'] ?? null,
                $this->request->getIpOrigem()
            );

            return ResponseFormatter::success(
                $this->serializarProposta($proposta),
                200,
                'Proposta cancelada com sucesso'
            );
        } catch (\Throwable $e) {
            return ExceptionHandler::handle($e)[0];
        }
    }

    /**
     * Valida dados de criação de proposta
     */
    private function validarCriacaoProposta(array $request): void
    {
        if (empty($request['cliente_id'])) {
            throw new \InvalidArgumentException('cliente_id é obrigatório');
        }

        if (!is_numeric($request['cliente_id'])) {
            throw new \InvalidArgumentException('cliente_id deve ser um número');
        }

        if (empty($request['valor'])) {
            throw new \InvalidArgumentException('valor é obrigatório');
        }

        if (!is_numeric($request['valor'])) {
            throw new \InvalidArgumentException('valor deve ser um número');
        }

        if ((float) $request['valor'] <= 0) {
            throw new \InvalidArgumentException('valor deve ser maior que zero');
        }
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
     * Serializa proposta para resposta JSON
     */
    private function serializarProposta($proposta): array
    {
        return [
            'id' => $proposta->getId(),
            'cliente' => [
                'nome' => $proposta->getCliente()->getNome(),
                'email' => $proposta->getCliente()->getEmail(),
                'documento' => $proposta->getCliente()->getDocumento(),
            ],
            'valor' => $proposta->getValor()->getValor(),
            'estado' => $proposta->getEstado()->value,
            'versao' => $proposta->getVersao(),
            'idempotencia_key' => $proposta->getIdempotenciaKey()?->getKey(),
            'created_at' => $proposta->getCriadoEm()->format('Y-m-d H:i:s'),
            'updated_at' => $proposta->getAtualizadoEm()?->format('Y-m-d H:i:s'),
        ];
    }
}
