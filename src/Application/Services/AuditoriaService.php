<?php

namespace App\Application\Services;

use App\Domain\Auditoria\Auditoria;
use App\Domain\Auditoria\AuditoriaRepositoryInterface;
use App\Domain\Auditoria\EventoAuditoria;

/**
 * Service: Auditoria
 * 
 * Responsável por registro automático de auditoria com eventos tipados
 * e payload em JSON para rastreabilidade completa.
 */
class AuditoriaService
{
    public function __construct(
        private AuditoriaRepositoryInterface $auditoriaRepository
    ) {
    }

    /**
     * Registra evento CREATED
     * 
     * @param string $entidadeTipo Tipo da entidade (ex: "Proposta", "Cliente")
     * @param int|null $entidadeId ID da entidade (pode ser null se ainda não foi salva)
     * @param array $dadosCompletos Dados completos da entidade criada
     * @param string|null $usuario Usuário que criou
     * @param string|null $ipOrigem IP de origem da requisição
     * @return Auditoria
     */
    public function registrarCriacao(
        string $entidadeTipo,
        ?int $entidadeId,
        array $dadosCompletos,
        ?string $usuario = null,
        ?string $ipOrigem = null
    ): Auditoria {
        $auditoria = new Auditoria(
            $entidadeTipo,
            $entidadeId,
            EventoAuditoria::CREATED,
            [], // CREATED não requer dados anteriores
            $dadosCompletos, // Dados completos da entidade criada
            $usuario,
            $ipOrigem
        );

        $this->auditoriaRepository->registrar($auditoria);
        return $auditoria;
    }

    /**
     * Registra evento UPDATED_FIELDS
     * 
     * Registra apenas os campos que foram alterados (diff).
     * 
     * @param string $entidadeTipo Tipo da entidade
     * @param int $entidadeId ID da entidade
     * @param array $dadosAnteriores Dados antes da atualização
     * @param array $dadosNovos Dados após a atualização
     * @param string|null $usuario Usuário que atualizou
     * @param string|null $ipOrigem IP de origem
     * @return Auditoria
     */
    public function registrarAtualizacao(
        string $entidadeTipo,
        int $entidadeId,
        array $dadosAnteriores,
        array $dadosNovos,
        ?string $usuario = null,
        ?string $ipOrigem = null
    ): Auditoria {
        // Calcula diff (apenas campos alterados)
        $diff = $this->calcularDiff($dadosAnteriores, $dadosNovos);

        // Se não houve alteração real, não registra
        if (empty($diff['anteriores']) && empty($diff['novos'])) {
            // Pode optar por retornar null ou lançar exceção
            // Por ora, registra mesmo assim para rastreabilidade
        }

        $auditoria = new Auditoria(
            $entidadeTipo,
            $entidadeId,
            EventoAuditoria::UPDATED_FIELDS,
            $diff['anteriores'] ?? $dadosAnteriores, // Usa diff se disponível
            $diff['novos'] ?? $dadosNovos,
            $usuario,
            $ipOrigem
        );

        $this->auditoriaRepository->registrar($auditoria);
        return $auditoria;
    }

    /**
     * Registra evento STATUS_CHANGED
     * 
     * @param string $entidadeTipo Tipo da entidade
     * @param int $entidadeId ID da entidade
     * @param string $estadoAnterior Estado anterior
     * @param string $estadoNovo Estado novo
     * @param array $dadosAnteriores Dados completos antes da mudança
     * @param array $dadosNovos Dados completos após a mudança
     * @param string|null $usuario Usuário que mudou o estado
     * @param string|null $ipOrigem IP de origem
     * @return Auditoria
     */
    public function registrarMudancaEstado(
        string $entidadeTipo,
        int $entidadeId,
        string $estadoAnterior,
        string $estadoNovo,
        array $dadosAnteriores,
        array $dadosNovos,
        ?string $usuario = null,
        ?string $ipOrigem = null
    ): Auditoria {
        // Adiciona informações de estado ao payload
        $payloadAnterior = array_merge($dadosAnteriores, [
            'estado' => $estadoAnterior,
            'estado_anterior' => $estadoAnterior,
        ]);

        $payloadNovo = array_merge($dadosNovos, [
            'estado' => $estadoNovo,
            'estado_novo' => $estadoNovo,
            'estado_anterior' => $estadoAnterior,
        ]);

        $auditoria = new Auditoria(
            $entidadeTipo,
            $entidadeId,
            EventoAuditoria::STATUS_CHANGED,
            $payloadAnterior,
            $payloadNovo,
            $usuario,
            $ipOrigem
        );

        $this->auditoriaRepository->registrar($auditoria);
        return $auditoria;
    }

    /**
     * Registra evento DELETED_LOGICAL
     * 
     * @param string $entidadeTipo Tipo da entidade
     * @param int $entidadeId ID da entidade
     * @param array $dadosCompletos Dados completos antes da exclusão
     * @param string|null $usuario Usuário que deletou
     * @param string|null $ipOrigem IP de origem
     * @return Auditoria
     */
    public function registrarExclusaoLogica(
        string $entidadeTipo,
        int $entidadeId,
        array $dadosCompletos,
        ?string $usuario = null,
        ?string $ipOrigem = null
    ): Auditoria {
        $auditoria = new Auditoria(
            $entidadeTipo,
            $entidadeId,
            EventoAuditoria::DELETED_LOGICAL,
            $dadosCompletos, // Dados completos antes da exclusão
            [], // DELETED_LOGICAL não requer dados novos
            $usuario,
            $ipOrigem
        );

        $this->auditoriaRepository->registrar($auditoria);
        return $auditoria;
    }

    /**
     * Calcula diferença entre dois arrays (diff)
     * 
     * Retorna apenas os campos que foram alterados.
     * 
     * @param array $antes Dados anteriores
     * @param array $depois Dados novos
     * @return array ['anteriores' => [...], 'novos' => [...]]
     */
    private function calcularDiff(array $antes, array $depois): array
    {
        $anteriores = [];
        $novos = [];

        // Compara chaves existentes em ambos
        foreach ($antes as $chave => $valorAntigo) {
            if (array_key_exists($chave, $depois)) {
                $valorNovo = $depois[$chave];
                
                // Se valores são diferentes (incluindo arrays)
                if ($this->saoDiferentes($valorAntigo, $valorNovo)) {
                    $anteriores[$chave] = $valorAntigo;
                    $novos[$chave] = $valorNovo;
                }
            } else {
                // Campo removido
                $anteriores[$chave] = $valorAntigo;
            }
        }

        // Campos novos (não existiam antes)
        foreach ($depois as $chave => $valorNovo) {
            if (!array_key_exists($chave, $antes)) {
                $novos[$chave] = $valorNovo;
            }
        }

        return [
            'anteriores' => $anteriores,
            'novos' => $novos,
        ];
    }

    /**
     * Compara dois valores (incluindo arrays recursivamente)
     */
    private function saoDiferentes($valor1, $valor2): bool
    {
        // Para arrays, compara recursivamente
        if (is_array($valor1) && is_array($valor2)) {
            return json_encode($valor1) !== json_encode($valor2);
        }

        // Para objetos, compara serializados
        if (is_object($valor1) && is_object($valor2)) {
            return serialize($valor1) !== serialize($valor2);
        }

        // Comparação simples
        return $valor1 !== $valor2;
    }
}
