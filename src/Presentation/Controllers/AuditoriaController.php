<?php

namespace App\Presentation\Controllers;

use App\Application\UseCases\BuscarAuditoriaUseCase;
use App\Presentation\Http\ResponseFormatter;
use App\Presentation\Http\ExceptionHandler;

/**
 * Controller REST para Auditoria
 */
class AuditoriaController
{
    public function __construct(
        private BuscarAuditoriaUseCase $buscarAuditoriaUseCase
    ) {
    }

    /**
     * GET /api/v1/auditoria/{entidadeTipo}/{entidadeId?}
     * GET /api/v1/propostas/{id}/auditoria
     */
    public function buscar(string $entidadeTipo, ?int $entidadeId = null): array
    {
        try {
            $auditorias = $this->buscarAuditoriaUseCase->executar($entidadeTipo, $entidadeId);

            return ResponseFormatter::success(
                array_map(
                    fn($auditoria) => $this->serializarAuditoria($auditoria),
                    $auditorias
                )
            );
        } catch (\Throwable $e) {
            return ExceptionHandler::handle($e)[0];
        }
    }

    private function serializarAuditoria($auditoria): array
    {
        return [
            'id' => $auditoria->getId(),
            'entidade_tipo' => $auditoria->getEntidadeTipo(),
            'entidade_id' => $auditoria->getEntidadeId(),
            'evento' => $auditoria->getEvento()->value,
            'dados_anteriores' => $auditoria->getDadosAnteriores(),
            'dados_novos' => $auditoria->getDadosNovos(),
            'usuario' => $auditoria->getUsuario(),
            'ip_origem' => $auditoria->getIpOrigem(),
            'ocorrido_em' => $auditoria->getOcorridoEm()->format('Y-m-d H:i:s'),
        ];
    }
}
