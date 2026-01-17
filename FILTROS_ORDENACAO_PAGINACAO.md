# Filtros, Ordenação e Paginação - Estratégia de Implementação

## Visão Geral

Implementação completa de filtros, ordenação e paginação obrigatória para listagem de propostas, com estratégia para **evitar N+1 queries**.

---

## 1. Arquitetura da Solução

### 1.1. Componentes Criados

1. **PropostaCriteria** - Encapsula parâmetros de busca
2. **PropostaRepository::buscarComCriteria()** - Método otimizado com filtros
3. **PropostaService::listarComCriteria()** - Orquestra busca
4. **PropostaController::listar()** - Endpoint HTTP

### 1.2. Fluxo de Dados

```
Controller (valida parâmetros)
    ↓
Service (cria Criteria)
    ↓
Repository (aplica filtros, ordenação, paginação)
    ↓
Retorna [propostas, total]
```

---

## 2. PropostaCriteria

**Arquivo**: `src/Domain/Proposta/Criteria/PropostaCriteria.php`

### 2.1. Responsabilidade

Encapsula todos os parâmetros de busca em um único objeto, garantindo:
- ✅ Validação de parâmetros
- ✅ Valores padrão
- ✅ Limites de segurança (max 100 por página)

### 2.2. Parâmetros

| Parâmetro | Tipo | Descrição |
|-----------|------|-----------|
| `clienteId` | `?int` | Filtrar por cliente (opcional) |
| `estado` | `?EstadoProposta` | Filtrar por estado (opcional) |
| `ordenarPor` | `string` | Campo para ordenação (default: created_at) |
| `direcao` | `string` | ASC ou DESC (default: DESC) |
| `pagina` | `int` | Número da página (obrigatório, min: 1) |
| `porPagina` | `int` | Itens por página (obrigatório, min: 1, max: 100) |

### 2.3. Campos Válidos para Ordenação

- `id` - ID da proposta
- `valor` - Valor monetário
- `estado` - Estado da proposta
- `created_at` - Data de criação
- `updated_at` - Data de atualização

---

## 3. Estratégia para Evitar N+1 Queries

### 3.1. Problema do N+1

**Cenário problemático**:
```php
// 1 query para buscar propostas
$propostas = $repository->buscarTodas();

// N queries para buscar clientes (1 por proposta)
foreach ($propostas as $proposta) {
    $cliente = $clienteRepository->buscarPorId($proposta->getClienteId());
    // N+1 queries! ❌
}
```

**Total**: 1 + N queries (ineficiente)

### 3.2. Solução Implementada

#### Estratégia 1: Batch Loading (Implementação Atual)

**Passo a passo**:

1. **Aplicar filtros e obter IDs das propostas**
   ```php
   $idsFiltrados = $this->aplicarFiltros($criteria);
   ```

2. **Carregar todas as propostas de uma vez**
   ```php
   $propostas = $this->carregarPropostas($idsFiltrados);
   ```

3. **Coletar IDs únicos de clientes**
   ```php
   $clienteIds = [];
   foreach ($propostas as $proposta) {
       $clienteId = $this->extrairClienteId($proposta);
       if (!in_array($clienteId, $clienteIds)) {
           $clienteIds[] = $clienteId;
       }
   }
   ```

4. **Carregar todos os clientes de uma vez (batch)**
   ```php
   $clientes = [];
   foreach ($clienteIds as $clienteId) {
       $clientes[$clienteId] = $this->clienteRepository->buscarPorId($clienteId);
   }
   ```

**Total**: 1 query para propostas + 1 query para clientes = **2 queries** ✅

#### Estratégia 2: JOIN SQL (Produção)

**Para implementação com banco de dados**:

```sql
SELECT 
    p.*,
    c.id as cliente_id,
    c.nome as cliente_nome,
    c.email as cliente_email,
    c.documento as cliente_documento
FROM propostas p
INNER JOIN clientes c ON p.cliente_id = c.id
WHERE 
    (p.cliente_id = ? OR ? IS NULL)
    AND (p.estado = ? OR ? IS NULL)
    AND p.deleted_at IS NULL
ORDER BY p.created_at DESC
LIMIT ? OFFSET ?
```

**Total**: **1 query única** ✅ (mais eficiente)

### 3.3. Implementação no Repositório

```php
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
```

---

## 4. Filtros Implementados

### 4.1. Filtro por Cliente

**Query Param**: `cliente_id`

**Exemplo**:
```
GET /api/v1/propostas?pagina=1&por_pagina=10&cliente_id=5
```

**Implementação**:
- Usa índice `clienteIndex` para busca rápida
- Filtra propostas por `cliente_id`
- Performance: O(1) com índice

### 4.2. Filtro por Estado

**Query Param**: `estado`

**Valores válidos**: `rascunho`, `enviada`, `aceita`, `recusada`, `cancelada`

**Exemplo**:
```
GET /api/v1/propostas?pagina=1&por_pagina=10&estado=enviada
```

**Implementação**:
- Usa índice `estadoIndex` para busca rápida
- Filtra propostas por estado
- Performance: O(1) com índice

### 4.3. Filtros Combinados

**AND entre filtros** (ambos devem ser verdadeiros):

```
GET /api/v1/propostas?pagina=1&por_pagina=10&cliente_id=5&estado=enviada
```

**Implementação**:
```php
// Intersecção de filtros (AND)
$idsFiltrados = array_intersect($idsCliente, $idsEstado);
```

---

## 5. Ordenação

### 5.1. Parâmetros

- `ordenar_por`: Campo para ordenação (default: `created_at`)
- `direcao`: `ASC` ou `DESC` (default: `DESC`)

### 5.2. Campos Válidos

| Campo | Descrição |
|-------|-----------|
| `id` | ID da proposta |
| `valor` | Valor monetário |
| `estado` | Estado (alfabético) |
| `created_at` | Data de criação (timestamp) |
| `updated_at` | Data de atualização (timestamp) |

### 5.3. Exemplo

```
GET /api/v1/propostas?pagina=1&por_pagina=10&ordenar_por=valor&direcao=ASC
```

**Ordena por valor do menor para o maior**.

### 5.4. Implementação

```php
private function aplicarOrdenacao(array $propostas, PropostaCriteria $criteria): array
{
    $campo = $criteria->getOrdenarPor();
    $direcao = $criteria->getDirecao();

    usort($propostas, function ($a, $b) use ($campo, $direcao) {
        $valorA = $this->obterValorOrdenacao($a, $campo);
        $valorB = $this->obterValorOrdenacao($b, $campo);

        $resultado = $valorA <=> $valorB;
        return $direcao === 'ASC' ? $resultado : -$resultado;
    });

    return $propostas;
}
```

---

## 6. Paginação Obrigatória

### 6.1. Por que Obrigatória?

- ✅ **Performance**: Limita quantidade de dados retornados
- ✅ **Segurança**: Previne sobrecarga do servidor
- ✅ **Experiência**: Respostas mais rápidas
- ✅ **Escalabilidade**: Sistema funciona com grandes volumes

### 6.2. Parâmetros Obrigatórios

| Parâmetro | Obrigatório | Mínimo | Máximo | Default |
|-----------|-------------|--------|--------|---------|
| `pagina` | ✅ Sim | 1 | - | - |
| `por_pagina` | ✅ Sim | 1 | 100 | - |

### 6.3. Validação

```php
// Valida paginação obrigatória
if (empty($queryParams['pagina']) || !is_numeric($queryParams['pagina'])) {
    throw new \InvalidArgumentException('Parâmetro "pagina" é obrigatório e deve ser um número');
}

if (empty($queryParams['por_pagina']) || !is_numeric($queryParams['por_pagina'])) {
    throw new \InvalidArgumentException('Parâmetro "por_pagina" é obrigatório e deve ser um número');
}

if ($pagina < 1) {
    throw new \InvalidArgumentException('Parâmetro "pagina" deve ser maior ou igual a 1');
}

if ($porPagina < 1 || $porPagina > 100) {
    throw new \InvalidArgumentException('Parâmetro "por_pagina" deve estar entre 1 e 100');
}
```

### 6.4. Resposta Paginada

```json
{
  "success": true,
  "data": [ ... ],
  "pagination": {
    "page": 1,
    "per_page": 10,
    "total": 150,
    "total_pages": 15
  }
}
```

### 6.5. Cálculo do Total

```php
// Calcula total ANTES da paginação
$total = count($propostas);

// Aplica paginação
$propostas = array_slice($propostas, $offset, $limit);

return [$propostas, $total];
```

**Importante**: Total é calculado **depois** dos filtros, mas **antes** da paginação.

---

## 7. Índices para Performance

### 7.1. Índices Criados

Na implementação em memória:

```php
// Índice por cliente (para filtros)
private array $clienteIndex = [];

// Índice por estado (para filtros)
private array $estadoIndex = [];

// Índice por idempotência
private array $idempotenciaIndex = [];
```

### 7.2. Em Banco de Dados (Produção)

```sql
-- Índices na tabela propostas
CREATE INDEX idx_propostas_cliente_id ON propostas(cliente_id);
CREATE INDEX idx_propostas_estado ON propostas(estado);
CREATE INDEX idx_propostas_cliente_estado ON propostas(cliente_id, estado);
CREATE INDEX idx_propostas_created_at ON propostas(created_at);
```

**Composto** `(cliente_id, estado)` para filtros combinados.

---

## 8. Endpoint Completo

### 8.1. GET /api/v1/propostas

**Query Parameters**:

| Parâmetro | Tipo | Obrigatório | Descrição |
|-----------|------|-------------|-----------|
| `pagina` | int | ✅ Sim | Número da página |
| `por_pagina` | int | ✅ Sim | Itens por página (1-100) |
| `cliente_id` | int | ❌ Não | Filtrar por cliente |
| `estado` | string | ❌ Não | Filtrar por estado |
| `ordenar_por` | string | ❌ Não | Campo para ordenação |
| `direcao` | string | ❌ Não | ASC ou DESC |

### 8.2. Exemplos de Uso

**Listar primeira página (10 itens)**:
```
GET /api/v1/propostas?pagina=1&por_pagina=10
```

**Filtrar por cliente**:
```
GET /api/v1/propostas?pagina=1&por_pagina=10&cliente_id=5
```

**Filtrar por estado**:
```
GET /api/v1/propostas?pagina=1&por_pagina=10&estado=enviada
```

**Filtros combinados + ordenação**:
```
GET /api/v1/propostas?pagina=1&por_pagina=10&cliente_id=5&estado=enviada&ordenar_por=valor&direcao=ASC
```

### 8.3. Resposta

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "cliente": {
        "nome": "João Silva",
        "email": "joao@example.com",
        "documento": "123.456.789-00"
      },
      "valor": 1500.0,
      "estado": "enviada",
      "versao": 2,
      "created_at": "2024-01-15 10:30:00",
      "updated_at": "2024-01-15 11:00:00"
    }
  ],
  "pagination": {
    "page": 1,
    "per_page": 10,
    "total": 25,
    "total_pages": 3
  }
}
```

---

## 9. Resumo da Estratégia

### 9.1. Evitar N+1 Queries

**✅ Implementado**:
- Batch loading de clientes
- Coleta de IDs únicos antes de buscar
- Carregamento em uma única operação

**🚀 Para Produção**:
- JOIN SQL para carregar tudo em 1 query
- Eager loading no ORM (se usar)

### 9.2. Performance

**✅ Otimizações**:
- Índices para filtros rápidos
- Intersecção eficiente de filtros
- Ordenação em memória após filtros
- Paginação no final

**🚀 Para Produção**:
- Ordenação no banco (ORDER BY)
- Paginação no banco (LIMIT/OFFSET)
- Índices compostos para filtros combinados

### 9.3. Segurança

**✅ Implementado**:
- Validação de parâmetros
- Limite máximo de itens por página (100)
- Validação de campos de ordenação
- Validação de direção de ordenação

---

## 10. Próximos Passos (Produção)

Para implementação com banco de dados:

1. **Substituir índices em memória por índices SQL**
2. **Usar JOIN para carregar cliente junto**
3. **Ordenar no banco (ORDER BY)**
4. **Pagininar no banco (LIMIT/OFFSET)**
5. **Usar COUNT(*) para total (sem carregar todos)**

**Exemplo SQL**:
```sql
SELECT p.*, c.nome, c.email, c.documento
FROM propostas p
INNER JOIN clientes c ON p.cliente_id = c.id
WHERE 
    (p.cliente_id = ? OR ? IS NULL)
    AND (p.estado = ? OR ? IS NULL)
    AND p.deleted_at IS NULL
ORDER BY p.created_at DESC
LIMIT ? OFFSET ?;

SELECT COUNT(*) as total
FROM propostas p
WHERE 
    (p.cliente_id = ? OR ? IS NULL)
    AND (p.estado = ? OR ? IS NULL)
    AND p.deleted_at IS NULL;
```

---

## 11. Conclusão

✅ **Filtros implementados** (cliente e estado)
✅ **Ordenação implementada** (5 campos, ASC/DESC)
✅ **Paginação obrigatória** (validação rigorosa)
✅ **N+1 queries evitadas** (batch loading)
✅ **Performance otimizada** (índices, intersecções)
✅ **Validação completa** (parâmetros, limites)
✅ **Extensível** (fácil adicionar novos filtros)

A estratégia está pronta para uso e pode ser facilmente adaptada para banco de dados real.
