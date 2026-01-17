<?php

namespace App\Application\UseCases;

use App\Domain\Proposta\Proposta;
use App\Domain\Proposta\PropostaRepositoryInterface;
use App\Domain\Proposta\ValueObjects\Cliente;
use App\Domain\Proposta\ValueObjects\Valor;
use App\Domain\Proposta\ValueObjects\IdempotenciaKey;
use App\Domain\Auditoria\Auditoria;
use App\Domain\Auditoria\AuditoriaRepositoryInterface;

/**
 * Caso de Uso: Criar Proposta
 * 
 * Implementa idempotência através da chave de idempotência.
 * Se uma proposta com a mesma chave já existe, retorna a existente.
 */
class CriarPropostaUseCase
{
    public function __construct(
        private PropostaRepositoryInterface $propostaRepository,
        private AuditoriaRepositoryInterface $auditoriaRepository
    ) {
    }

    public function executar(
        string $nomeCliente,
        string $emailCliente,
        ?string $documentoCliente,
        float $valor,
        ?string $idempotenciaKey = null,
        ?string $usuario = null
    ): Proposta {
        // Verifica idempotência
        if ($idempotenciaKey !== null) {
            $key = new IdempotenciaKey($idempotenciaKey);
            $propostaExistente = $this->propostaRepository->buscarPorIdempotenciaKey($key);
            
            if ($propostaExistente !== null) {
                // Retorna a proposta existente (idempotência)
                return $propostaExistente;
            }
        }

        // Cria nova proposta
        $cliente = new Cliente($nomeCliente, $emailCliente, $documentoCliente);
        $valorObj = new Valor($valor);
        $keyObj = $idempotenciaKey ? new IdempotenciaKey($idempotenciaKey) : null;

        $proposta = new Proposta($cliente, $valorObj, $keyObj);
        $this->propostaRepository->salvar($proposta);

        // Registra auditoria
        $auditoria = new Auditoria(
            'Proposta',
            $proposta->getId(),
            'CRIAR',
            [],
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
            'cliente' => [
                'nome' => $proposta->getCliente()->getNome(),
                'email' => $proposta->getCliente()->getEmail(),
                'documento' => $proposta->getCliente()->getDocumento(),
            ],
            'valor' => $proposta->getValor()->getValor(),
            'estado' => $proposta->getEstado()->value,
            'versao' => $proposta->getVersao(),
        ];
    }
}
