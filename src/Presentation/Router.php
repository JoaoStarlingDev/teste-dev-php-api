<?php

namespace App\Presentation;

use App\Presentation\Controllers\Api\V1\ClienteController;
use App\Presentation\Controllers\Api\V1\PropostaController;
use App\Presentation\Controllers\AuditoriaController;

/**
 * Roteador REST versionado
 * 
 * Gerencia as rotas da API versionada em /api/v1.
 */
class Router
{
    public function __construct(
        private ClienteController $clienteController,
        private PropostaController $propostaController,
        private AuditoriaController $auditoriaController
    ) {
    }

    /**
     * Processa requisição e retorna resposta formatada
     * 
     * @param string $method Método HTTP
     * @param string $path Caminho da requisição
     * @param array $dados Dados do body e query params
     * @return array [resposta, statusCode]
     */
    public function processar(string $method, string $path, array $dados = []): array
    {
        // Remove query string do path
        $path = parse_url($path, PHP_URL_PATH);

        // Separa query params dos dados
        $queryParams = [];
        $body = [];
        
        foreach ($dados as $key => $value) {
            if (in_array($key, ['pagina', 'por_pagina', 'page', 'per_page'])) {
                $queryParams[$key] = $value;
            } else {
                $body[$key] = $value;
            }
        }

        // Roteamento para /api/v1/clientes
        if (preg_match('#^/api/v1/clientes$#', $path)) {
            return match ($method) {
                'POST' => [$this->clienteController->criar($body), 201],
                default => $this->metodoNaoPermitido(),
            };
        }

        if (preg_match('#^/api/v1/clientes/(\d+)$#', $path, $matches)) {
            $id = (int)$matches[1];
            return match ($method) {
                'GET' => [$this->clienteController->buscar($id), 200],
                default => $this->metodoNaoPermitido(),
            };
        }

        // Roteamento para /api/v1/propostas
        if (preg_match('#^/api/v1/propostas$#', $path)) {
            return match ($method) {
                'GET' => [$this->propostaController->listar($queryParams), 200],
                'POST' => [$this->propostaController->criar($body), 201],
                default => $this->metodoNaoPermitido(),
            };
        }

        if (preg_match('#^/api/v1/propostas/(\d+)$#', $path, $matches)) {
            $id = (int)$matches[1];
            return match ($method) {
                'GET' => [$this->propostaController->buscar($id), 200],
                'PATCH' => [$this->propostaController->atualizar($id, $body), 200],
                default => $this->metodoNaoPermitido(),
            };
        }

        // Submeter proposta
        if (preg_match('#^/api/v1/propostas/(\d+)/submeter$#', $path, $matches)) {
            $id = (int)$matches[1];
            return match ($method) {
                'POST' => [$this->propostaController->submeter($id, $body), 200],
                default => $this->metodoNaoPermitido(),
            };
        }

        // Aprovar proposta
        if (preg_match('#^/api/v1/propostas/(\d+)/aprovar$#', $path, $matches)) {
            $id = (int)$matches[1];
            return match ($method) {
                'POST' => [$this->propostaController->aprovar($id, $body), 200],
                default => $this->metodoNaoPermitido(),
            };
        }

        // Rejeitar proposta
        if (preg_match('#^/api/v1/propostas/(\d+)/rejeitar$#', $path, $matches)) {
            $id = (int)$matches[1];
            return match ($method) {
                'POST' => [$this->propostaController->rejeitar($id, $body), 200],
                default => $this->metodoNaoPermitido(),
            };
        }

        // Cancelar proposta
        if (preg_match('#^/api/v1/propostas/(\d+)/cancelar$#', $path, $matches)) {
            $id = (int)$matches[1];
            return match ($method) {
                'POST' => [$this->propostaController->cancelar($id, $body), 200],
                default => $this->metodoNaoPermitido(),
            };
        }

        // Buscar auditoria de proposta
        if (preg_match('#^/api/v1/propostas/(\d+)/auditoria$#', $path, $matches)) {
            $id = (int)$matches[1];
            return match ($method) {
                'GET' => [$this->auditoriaController->buscar('Proposta', $id), 200],
                default => $this->metodoNaoPermitido(),
            };
        }

        // Rota não encontrada
        return $this->rotaNaoEncontrada();
    }

    /**
     * Retorna resposta de método não permitido
     */
    private function metodoNaoPermitido(): array
    {
        return [
            [
                'success' => false,
                'message' => 'Método não permitido',
            ],
            405
        ];
    }

    /**
     * Retorna resposta de rota não encontrada
     */
    private function rotaNaoEncontrada(): array
    {
        return [
            [
                'success' => false,
                'message' => 'Rota não encontrada',
            ],
            404
        ];
    }
}
