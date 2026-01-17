<?php

namespace App\Infrastructure\Repository;

use App\Domain\Idempotencia\IdempotenciaOperacao;
use App\Domain\Idempotencia\IdempotenciaOperacaoRepositoryInterface;

/**
 * Implementação do Repositório de Idempotência de Operações
 * 
 * Implementação em memória para demonstração.
 * Em produção, substituir por implementação com banco de dados.
 */
class IdempotenciaOperacaoRepository implements IdempotenciaOperacaoRepositoryInterface
{
    private array $operacoes = [];
    private array $keyTipoIndex = [];
    private int $nextId = 1;

    public function buscarPorKeyETipo(string $idempotenciaKey, string $tipoOperacao): ?IdempotenciaOperacao
    {
        $key = trim($idempotenciaKey);
        $tipo = trim($tipoOperacao);
        $indexKey = "{$key}::{$tipo}";

        if (!isset($this->keyTipoIndex[$indexKey])) {
            return null;
        }

        $id = $this->keyTipoIndex[$indexKey];
        return $this->operacoes[$id] ?? null;
    }

    public function salvar(IdempotenciaOperacao $operacao): void
    {
        if ($operacao->getId() === null) {
            $operacao->setId($this->nextId++);
        }

        $id = $operacao->getId();
        $this->operacoes[$id] = $operacao;

        // Indexa por chave + tipo
        $key = $operacao->getIdempotenciaKey();
        $tipo = $operacao->getTipoOperacao();
        $indexKey = "{$key}::{$tipo}";
        $this->keyTipoIndex[$indexKey] = $id;
    }
}
