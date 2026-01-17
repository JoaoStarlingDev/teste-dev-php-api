<?php

namespace App\Presentation\Http;

use App\Domain\Proposta\Exceptions\EstadoFinalImutavelException;
use App\Domain\Proposta\Exceptions\TransicaoEstadoInvalidaException;
use App\Domain\Proposta\Exceptions\PropostaNaoPodeSerEditadaException;
use App\Domain\Proposta\Exceptions\VersaoIncorretaException;

/**
 * Tratador de Exceções HTTP
 * 
 * Converte exceções de domínio em respostas HTTP apropriadas.
 */
class ExceptionHandler
{
    /**
     * Trata exceção e retorna resposta formatada
     * 
     * @param \Throwable $exception
     * @return array [response, statusCode]
     */
    public static function handle(\Throwable $exception): array
    {
        // Exceções de domínio específicas
        if ($exception instanceof EstadoFinalImutavelException) {
            return [
                ResponseFormatter::error($exception->getMessage(), 409),
                409
            ];
        }

        if ($exception instanceof TransicaoEstadoInvalidaException) {
            return [
                ResponseFormatter::error($exception->getMessage(), 422),
                422
            ];
        }

        if ($exception instanceof PropostaNaoPodeSerEditadaException) {
            return [
                ResponseFormatter::error($exception->getMessage(), 422),
                422
            ];
        }

        // VersaoIncorretaException (optimistic lock)
        if ($exception instanceof VersaoIncorretaException) {
            return [
                ResponseFormatter::conflict($exception->getMessage()),
                409
            ];
        }

        // DomainException (recurso não encontrado, etc)
        if ($exception instanceof \DomainException) {

            // Verifica se é recurso não encontrado
            if (str_contains($exception->getMessage(), 'não encontrado') || 
                str_contains($exception->getMessage(), 'não existe')) {
                return [
                    ResponseFormatter::notFound($exception->getMessage()),
                    404
                ];
            }

            return [
                ResponseFormatter::error($exception->getMessage(), 400),
                400
            ];
        }

        // InvalidArgumentException (validação)
        if ($exception instanceof \InvalidArgumentException) {
            return [
                ResponseFormatter::validationError(['geral' => $exception->getMessage()]),
                422
            ];
        }

        // Erro genérico
        return [
            ResponseFormatter::error('Erro interno do servidor', 500),
            500
        ];
    }
}
