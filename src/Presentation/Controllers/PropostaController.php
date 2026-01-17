<?php

namespace App\Presentation\Controllers;

use App\Application\UseCases\CriarPropostaUseCase;
use App\Application\UseCases\AtualizarPropostaUseCase;
use App\Application\UseCases\TransicionarEstadoPropostaUseCase;
use App\Application\UseCases\BuscarPropostaUseCase;
use App\Application\UseCases\ListarPropostasUseCase;
use App\Domain\Proposta\EstadoProposta;

/**
 * Controller REST para Propostas
 * 
 * Gerencia as requisições HTTP relacionadas a propostas.
 */
class PropostaController
{
    public function __construct(
        private CriarPropostaUseCase $criarPropostaUseCase,
        private AtualizarPropostaUseCase $atualizarPropostaUseCase,
        private TransicionarEstadoPropostaUseCase $transicionarEstadoUseCase,
        private BuscarPropostaUseCase $buscarPropostaUseCase,
        private ListarPropostasUseCase $listarPropostasUseCase
    ) {
    }

    /**
     * POST /api/v1/propostas
     */
    public function criar(array $dados): array
    {
        try {
            $proposta = $this->criarPropostaUseCase->executar(
                $dados['cliente']['nome'] ?? '',
                $dados['cliente']['email'] ?? '',
                $dados['cliente']['documento'] ?? null,
                $dados['valor'] ?? 0,
                $dados['idempotencia_key'] ?? null,
                $dados['usuario'] ?? null
            );

            return [
                'status' => 'success',
                'data' => $this->serializarProposta($proposta),
            ];
        } catch (\InvalidArgumentException $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * GET /api/v1/propostas/{id}
     */
    public function buscar(int $id): array
    {
        $proposta = $this->buscarPropostaUseCase->executar($id);

        if ($proposta === null) {
            return [
                'status' => 'error',
                'message' => 'Proposta não encontrada',
            ];
        }

        return [
            'status' => 'success',
            'data' => $this->serializarProposta($proposta),
        ];
    }

    /**
     * GET /api/v1/propostas
     */
    public function listar(int $pagina = 1, int $porPagina = 50): array
    {
        $propostas = $this->listarPropostasUseCase->executar($pagina, $porPagina);

        return [
            'status' => 'success',
            'data' => array_map(
                fn($proposta) => $this->serializarProposta($proposta),
                $propostas
            ),
            'paginacao' => [
                'pagina' => $pagina,
                'por_pagina' => $porPagina,
            ],
        ];
    }

    /**
     * PATCH /api/v1/propostas/{id}
     */
    public function atualizar(int $id, array $dados): array
    {
        try {
            $versaoEsperada = $dados['versao'] ?? throw new \InvalidArgumentException('Versão é obrigatória');

            $proposta = $this->atualizarPropostaUseCase->executar(
                $id,
                $dados['valor'] ?? null,
                $versaoEsperada,
                $dados['usuario'] ?? null
            );

            return [
                'status' => 'success',
                'data' => $this->serializarProposta($proposta),
            ];
        } catch (\DomainException | \InvalidArgumentException $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * POST /api/v1/propostas/{id}/transicionar-estado
     */
    public function transicionarEstado(int $id, array $dados): array
    {
        try {
            $estadoString = $dados['estado'] ?? throw new \InvalidArgumentException('Estado é obrigatório');
            $versaoEsperada = $dados['versao'] ?? throw new \InvalidArgumentException('Versão é obrigatória');

            $novoEstado = EstadoProposta::from($estadoString);

            $proposta = $this->transicionarEstadoUseCase->executar(
                $id,
                $novoEstado,
                $versaoEsperada,
                $dados['usuario'] ?? null
            );

            return [
                'status' => 'success',
                'data' => $this->serializarProposta($proposta),
            ];
        } catch (\ValueError | \DomainException | \InvalidArgumentException $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

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
            'criado_em' => $proposta->getCriadoEm()->format('Y-m-d H:i:s'),
            'atualizado_em' => $proposta->getAtualizadoEm()?->format('Y-m-d H:i:s'),
        ];
    }
}
