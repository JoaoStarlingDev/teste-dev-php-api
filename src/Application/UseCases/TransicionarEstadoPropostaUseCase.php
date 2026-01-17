<?php

namespace App\Application\UseCases;

use App\Domain\Proposta\Proposta;
use App\Domain\Proposta\EstadoProposta;
use App\Domain\Proposta\PropostaRepositoryInterface;
use App\Domain\Auditoria\Auditoria;
use App\Domain\Auditoria\AuditoriaRepositoryInterface;

/**
 * Caso de Uso: Transicionar Estado da Proposta
 * 
 * Controla as transições de estado seguindo as regras de negócio.
 * Implementa optimistic lock.
 */
class TransicionarEstadoPropostaUseCase
{
    public function __construct(
        private PropostaRepositoryInterface $propostaRepository,
        private AuditoriaRepositoryInterface $auditoriaRepository
    ) {
    }

    public function executar(
        int $id,
        EstadoProposta $novoEstado,
        int $versaoEsperada,
        ?string $usuario = null
    ): Proposta {
        $proposta = $this->propostaRepository->buscarPorId($id);

        if ($proposta === null) {
            throw new \DomainException("Proposta não encontrada");
        }

        // Optimistic lock
        if (!$proposta->verificarVersao($versaoEsperada)) {
            throw new \DomainException(
                "Proposta foi modificada por outro processo. Versão atual: {$proposta->getVersao()}"
            );
        }

        $estadoAnterior = $proposta->getEstado();
        $dadosAnteriores = $this->serializarProposta($proposta);

        // Transiciona estado (validação de regras de negócio dentro da entidade)
        $proposta->transicionarEstado($novoEstado);

        $this->propostaRepository->salvar($proposta);

        // Registra auditoria
        $auditoria = new Auditoria(
            'Proposta',
            $proposta->getId(),
            'TRANSIÇÃO_ESTADO',
            array_merge($dadosAnteriores, ['estado_anterior' => $estadoAnterior->value]),
            array_merge($this->serializarProposta($proposta), ['estado_anterior' => $estadoAnterior->value]),
            $usuario
        );
        $this->auditoriaRepository->registrar($auditoria);

        return $proposta;
    }

    private function serializarProposta(Proposta $proposta): array
    {
        return [
            'id' => $proposta->getId(),
            'estado' => $proposta->getEstado()->value,
            'versao' => $proposta->getVersao(),
        ];
    }
}
