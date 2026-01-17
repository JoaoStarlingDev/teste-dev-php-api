<?php

namespace App\Domain\Proposta;

use App\Domain\Proposta\ValueObjects\IdempotenciaKey;
use App\Domain\Proposta\Criteria\PropostaCriteria;

/**
 * Interface do Repositório de Propostas
 */
interface PropostaRepositoryInterface
{
    public function salvar(Proposta $proposta): void;
    public function buscarPorId(int $id): ?Proposta;
    public function buscarPorIdempotenciaKey(IdempotenciaKey $key): ?Proposta;
    
    /**
     * Busca propostas com filtros, ordenação e paginação
     * 
     * @param PropostaCriteria $criteria
     * @return array [Proposta[], total]
     */
    public function buscarComCriteria(PropostaCriteria $criteria): array;
    
    /**
     * @deprecated Use buscarComCriteria() ao invés
     */
    public function buscarTodos(int $offset = 0, int $limit = 50): array;
}
