<?php

namespace App\Domain\Proposta;

use App\Domain\Proposta\Exceptions\EstadoFinalImutavelException;
use App\Domain\Proposta\Exceptions\TransicaoEstadoInvalidaException;
use App\Domain\Proposta\Exceptions\PropostaNaoPodeSerEditadaException;

/**
 * Validador de Transições de Estado
 * 
 * Responsável por validar todas as transições de estado da proposta,
 * garantindo que apenas transições válidas sejam permitidas e que
 * estados finais sejam imutáveis.
 * 
 * Regras de validação:
 * 1. Estados finais não permitem transições
 * 2. Apenas transições definidas na FSM são válidas
 * 3. Estados finais não permitem edição
 */
class ValidadorTransicaoEstado
{
    /**
     * Valida se uma transição de estado é permitida
     * 
     * @param EstadoProposta $estadoAtual
     * @param EstadoProposta $novoEstado
     * @throws TransicaoEstadoInvalidaException
     * @throws EstadoFinalImutavelException
     * @return void
     */
    public function validarTransicao(EstadoProposta $estadoAtual, EstadoProposta $novoEstado): void
    {
        // Regra 1: Estados finais são imutáveis
        if ($estadoAtual->isFinal()) {
            throw new EstadoFinalImutavelException(
                $estadoAtual,
                "Não é possível transicionar de um estado final. Estado atual: {$estadoAtual->value}"
            );
        }

        // Regra 2: Não pode transicionar para o mesmo estado
        if ($estadoAtual === $novoEstado) {
            throw new TransicaoEstadoInvalidaException(
                $estadoAtual,
                $novoEstado,
                "Não é possível transicionar para o mesmo estado: {$estadoAtual->value}"
            );
        }

        // Regra 3: Verifica se a transição é válida segundo a FSM
        if (!$estadoAtual->podeTransicionarPara($novoEstado)) {
            $estadosValidos = array_map(
                fn($e) => $e->value,
                $estadoAtual->estadosValidosParaTransicao()
            );

            throw new TransicaoEstadoInvalidaException(
                $estadoAtual,
                $novoEstado,
                sprintf(
                    "Transição inválida de '%s' para '%s'. Estados válidos: %s",
                    $estadoAtual->value,
                    $novoEstado->value,
                    implode(', ', $estadosValidos)
                )
            );
        }
    }

    /**
     * Valida se o estado atual permite edição
     * 
     * @param EstadoProposta $estadoAtual
     * @throws PropostaNaoPodeSerEditadaException
     * @return void
     */
    public function validarPermissaoEdicao(EstadoProposta $estadoAtual): void
    {
        if (!$estadoAtual->permiteEdicao()) {
            throw new PropostaNaoPodeSerEditadaException(
                $estadoAtual,
                "Proposta não pode ser editada no estado '{$estadoAtual->value}'. Apenas propostas em 'rascunho' podem ser editadas."
            );
        }
    }

    /**
     * Valida se o estado atual permite cancelamento
     * 
     * @param EstadoProposta $estadoAtual
     * @throws EstadoFinalImutavelException
     * @return void
     */
    public function validarPermissaoCancelamento(EstadoProposta $estadoAtual): void
    {
        if ($estadoAtual->isFinal()) {
            throw new EstadoFinalImutavelException(
                $estadoAtual,
                "Não é possível cancelar proposta no estado '{$estadoAtual->value}'. Estados finais são imutáveis."
            );
        }
    }

    /**
     * Valida se o estado atual permite qualquer alteração
     * 
     * Estados finais não permitem nenhuma alteração.
     * 
     * @param EstadoProposta $estadoAtual
     * @throws EstadoFinalImutavelException
     * @return void
     */
    public function validarPermissaoAlteracao(EstadoProposta $estadoAtual): void
    {
        if ($estadoAtual->isFinal()) {
            throw new EstadoFinalImutavelException(
                $estadoAtual,
                "Não é possível alterar proposta no estado '{$estadoAtual->value}'. Estados finais são imutáveis."
            );
        }
    }

    /**
     * Retorna mensagem descritiva sobre as transições válidas
     * 
     * @param EstadoProposta $estadoAtual
     * @return string
     */
    public function obterTransicoesValidas(EstadoProposta $estadoAtual): string
    {
        if ($estadoAtual->isFinal()) {
            return "Estado final '{$estadoAtual->value}' não permite transições.";
        }

        $estadosValidos = $estadoAtual->estadosValidosParaTransicao();
        $estadosValidosStr = array_map(fn($e) => $e->value, $estadosValidos);

        return sprintf(
            "Do estado '%s' é possível transicionar para: %s",
            $estadoAtual->value,
            implode(', ', $estadosValidosStr)
        );
    }
}
