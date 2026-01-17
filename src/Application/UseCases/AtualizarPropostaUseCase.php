<?php

namespace App\Application\UseCases;

use App\Domain\Proposta\Proposta;
use App\Domain\Proposta\PropostaRepositoryInterface;
use App\Domain\Proposta\ValueObjects\Valor;
use App\Domain\Auditoria\Auditoria;
use App\Domain\Auditoria\AuditoriaRepositoryInterface;

/**
 * Caso de Uso: Atualizar Proposta
 * 
 * Implementa optimistic lock através do controle de versão.
 */
class AtualizarPropostaUseCase
{
    public function __construct(
        private PropostaRepositoryInterface $propostaRepository,
        private AuditoriaRepositoryInterface $auditoriaRepository
    ) {
    }

    public function executar(
        int $id,
        ?float $valor = null,
        int $versaoEsperada,
        ?string $usuario = null
    ): Proposta {
        $proposta = $this->propostaRepository->buscarPorId($id);

        if ($proposta === null) {
            throw new \DomainException("Proposta não encontrada");
        }

        // Optimistic lock: verifica se a versão ainda é a mesma
        if (!$proposta->verificarVersao($versaoEsperada)) {
            throw new \DomainException(
                "Proposta foi modificada por outro processo. Versão atual: {$proposta->getVersao()}"
            );
        }

        $dadosAnteriores = $this->serializarProposta($proposta);

        // Atualiza valor se fornecido
        if ($valor !== null) {
            $novoValor = new Valor($valor);
            $proposta->atualizarValor($novoValor);
        }

        $this->propostaRepository->salvar($proposta);

        // Registra auditoria
        $auditoria = new Auditoria(
            'Proposta',
            $proposta->getId(),
            'ATUALIZAR',
            $dadosAnteriores,
            $this->serializarProposta($proposta),
            $usuario
        );
        $this->auditoriaRepository->registrar($auditoria);

        return $proposta;
    }

    private function serializarProposta(Proposta $proposta): array
    {
        return [
            'id' => $proposta->getId(),
            'valor' => $proposta->getValor()->getValor(),
            'estado' => $proposta->getEstado()->value,
            'versao' => $proposta->getVersao(),
        ];
    }
}
