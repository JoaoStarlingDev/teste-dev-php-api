<?php

namespace App\Domain\Idempotencia;

/**
 * Interface do Repositório de Idempotência de Operações
 */
interface IdempotenciaOperacaoRepositoryInterface
{
    /**
     * Busca resultado de operação idempotente
     * 
     * @param string $idempotenciaKey
     * @param string $tipoOperacao
     * @return IdempotenciaOperacao|null
     */
    public function buscarPorKeyETipo(string $idempotenciaKey, string $tipoOperacao): ?IdempotenciaOperacao;

    /**
     * Salva resultado de operação idempotente
     * 
     * @param IdempotenciaOperacao $operacao
     * @return void
     */
    public function salvar(IdempotenciaOperacao $operacao): void;
}
