<?php

namespace App\Infrastructure\Repository;

use App\Domain\Proposta\Proposta;
use App\Domain\Proposta\PropostaRepositoryInterface;
use App\Domain\Proposta\ValueObjects\IdempotenciaKey;
use App\Domain\Proposta\Criteria\PropostaCriteria;

/**
 * Implementação do Repositório de Propostas
 * 
 * Implementação em memória para demonstração.
 * Em produção, substituir por implementação com banco de dados usando JOIN para evitar N+1.
 */
class PropostaRepository implements PropostaRepositoryInterface
{
    private array $propostas = [];
    private array $idempotenciaIndex = [];
    private array $clienteIndex = []; // [cliente_id => [proposta_ids]]
    private array $estadoIndex = []; // [estado => [proposta_ids]]
    private int $nextId = 1;

    public function salvar(Proposta $proposta): void
    {
        $idAnterior = $proposta->getId();
        $isUpdate = $idAnterior !== null;

        if ($proposta->getId() === null) {
            $proposta->setId($this->nextId++);
        }

        $id = $proposta->getId();
        $this->propostas[$id] = $proposta;

        // Remove índices antigos se for update (evita duplicatas)
        if ($isUpdate) {
            $this->removerDosIndices($idAnterior);
        }

        // Indexa por chave de idempotência
        if ($proposta->getIdempotenciaKey() !== null) {
            $key = $proposta->getIdempotenciaKey()->getKey();
            $this->idempotenciaIndex[$key] = $id;
        }

        // Indexa por cliente (para filtros) - agora usa clienteId direto
        $clienteId = $proposta->getClienteId();
        if (!isset($this->clienteIndex[$clienteId])) {
            $this->clienteIndex[$clienteId] = [];
        }
        // Só adiciona se não existir (evita duplicatas)
        if (!in_array($id, $this->clienteIndex[$clienteId], true)) {
            $this->clienteIndex[$clienteId][] = $id;
        }

        // Indexa por estado (para filtros)
        $estado = $proposta->getEstado()->value;
        if (!isset($this->estadoIndex[$estado])) {
            $this->estadoIndex[$estado] = [];
        }
        // Só adiciona se não existir (evita duplicatas)
        if (!in_array($id, $this->estadoIndex[$estado], true)) {
            $this->estadoIndex[$estado][] = $id;
        }
    }

    public function buscarPorId(int $id): ?Proposta
    {
        return $this->propostas[$id] ?? null;
    }

    public function buscarPorIdempotenciaKey(IdempotenciaKey $key): ?Proposta
    {
        $keyString = $key->getKey();
        
        if (!isset($this->idempotenciaIndex[$keyString])) {
            return null;
        }

        $id = $this->idempotenciaIndex[$keyString];
        return $this->propostas[$id] ?? null;
    }

    /**
     * Busca propostas com filtros, ordenação e paginação
     * 
     * Retorna [propostas, total] para evitar N+1 queries.
     * Em implementação com banco de dados, usar JOIN para carregar cliente junto.
     */
    public function buscarComCriteria(PropostaCriteria $criteria): array
    {
        // 1. Aplica filtros
        $idsFiltrados = $this->aplicarFiltros($criteria);

        // 2. Carrega propostas (evita N+1 carregando todas de uma vez)
        $propostas = $this->carregarPropostas($idsFiltrados);

        // 3. Carrega clientes em batch (evita N+1)
        $propostas = $this->carregarClientesEmBatch($propostas);

        // 4. Aplica ordenação
        $propostas = $this->aplicarOrdenacao($propostas, $criteria);

        // 5. Calcula total antes da paginação
        $total = count($propostas);

        // 6. Aplica paginação
        $propostas = $this->aplicarPaginação($propostas, $criteria);

        return [$propostas, $total];
    }

    /**
     * Aplica filtros e retorna array de IDs
     */
    private function aplicarFiltros(PropostaCriteria $criteria): array
    {
        $idsPorFiltro = [];

        // Filtro por cliente
        if ($criteria->getClienteId() !== null) {
            $clienteId = $criteria->getClienteId();
            if (isset($this->clienteIndex[$clienteId])) {
                $idsPorFiltro['cliente'] = $this->clienteIndex[$clienteId];
            } else {
                // Cliente não tem propostas
                return [];
            }
        }

        // Filtro por estado
        if ($criteria->getEstado() !== null) {
            $estado = $criteria->getEstado()->value;
            if (isset($this->estadoIndex[$estado])) {
                $idsPorFiltro['estado'] = $this->estadoIndex[$estado];
            } else {
                // Estado não tem propostas
                return [];
            }
        }

        // Se não há filtros, retorna todos os IDs
        if (empty($idsPorFiltro)) {
            return array_keys($this->propostas);
        }

        // Intersecção de filtros (AND)
        $idsFiltrados = array_shift($idsPorFiltro);
        foreach ($idsPorFiltro as $ids) {
            $idsFiltrados = array_intersect($idsFiltrados, $ids);
        }

        return array_values($idsFiltrados);
    }

    /**
     * Carrega propostas pelos IDs (evita N+1 carregando todas de uma vez)
     */
    private function carregarPropostas(array $ids): array
    {
        $propostas = [];
        foreach ($ids as $id) {
            if (isset($this->propostas[$id])) {
                $propostas[] = $this->propostas[$id];
            }
        }
        return $propostas;
    }

    /**
     * Carrega clientes em batch para evitar N+1 queries
     * 
     * Em implementação com banco de dados, isso seria feito via JOIN:
     * SELECT p.*, c.nome, c.email, c.documento
     * FROM propostas p
     * INNER JOIN clientes c ON p.cliente_id = c.id
     * WHERE ...
     * 
     * Por enquanto, apenas retorna as propostas (o clienteId já está disponível).
     * Se necessário carregar dados completos do cliente, isso seria feito aqui.
     */
    private function carregarClientesEmBatch(array $propostas): array
    {
        // Em implementação com banco de dados, aqui seria feito JOIN ou batch load
        // Por enquanto, apenas retorna as propostas (clienteId já está disponível)
        return $propostas;
    }

    /**
     * Aplica ordenação
     */
    private function aplicarOrdenacao(array $propostas, PropostaCriteria $criteria): array
    {
        $campo = $criteria->getOrdenarPor();
        $direcao = $criteria->getDirecao();

        usort($propostas, function ($a, $b) use ($campo, $direcao) {
            $valorA = $this->obterValorOrdenacao($a, $campo);
            $valorB = $this->obterValorOrdenacao($b, $campo);

            if ($valorA === $valorB) {
                return 0;
            }

            $resultado = $valorA <=> $valorB;
            return $direcao === 'ASC' ? $resultado : -$resultado;
        });

        return $propostas;
    }

    /**
     * Obtém valor para ordenação
     */
    private function obterValorOrdenacao(Proposta $proposta, string $campo): mixed
    {
        return match ($campo) {
            'id' => $proposta->getId(),
            'valor' => $proposta->getValor()->getValor(),
            'estado' => $proposta->getEstado()->value,
            'created_at' => $proposta->getCriadoEm()->getTimestamp(),
            'updated_at' => $proposta->getAtualizadoEm()?->getTimestamp() ?? 0,
            default => 0,
        };
    }

    /**
     * Aplica paginação
     */
    private function aplicarPaginação(array $propostas, PropostaCriteria $criteria): array
    {
        $offset = $criteria->getOffset();
        $limit = $criteria->getPorPagina();

        return array_slice($propostas, $offset, $limit);
    }


    /**
     * Remove proposta dos índices (usado em updates)
     */
    private function removerDosIndices(int $id): void
    {
        // Remove de clienteIndex
        foreach ($this->clienteIndex as $clienteId => &$ids) {
            $ids = array_values(array_filter($ids, fn($i) => $i !== $id));
            if (empty($ids)) {
                unset($this->clienteIndex[$clienteId]);
            }
        }
        unset($ids); // Remove referência

        // Remove de estadoIndex
        foreach ($this->estadoIndex as $estado => &$ids) {
            $ids = array_values(array_filter($ids, fn($i) => $i !== $id));
            if (empty($ids)) {
                unset($this->estadoIndex[$estado]);
            }
        }
        unset($ids); // Remove referência
    }

    /**
     * @deprecated Use buscarComCriteria() ao invés
     */
    public function buscarTodos(int $offset = 0, int $limit = 50): array
    {
        $criteria = new PropostaCriteria(null, null, 'created_at', 'DESC', 
            (int) floor($offset / $limit) + 1, $limit);
        [$propostas] = $this->buscarComCriteria($criteria);
        return $propostas;
    }
}
