# Modelo Relacional - Sistema de Gestão de Propostas

## 1. Visão Geral do Modelo

O modelo relacional é composto por **3 tabelas principais**:
- `clientes` - Dados dos clientes
- `propostas` - Propostas comerciais
- `auditoria_propostas` - Histórico de alterações

---

## 2. Tabelas e Estrutura

### 2.1. Tabela: `clientes`

**Descrição**: Armazena os dados dos clientes que recebem propostas.

| Campo | Tipo | Tamanho | Null | Default | Descrição |
|-------|------|---------|------|---------|-----------|
| `id` | INT | - | NOT NULL | AUTO_INCREMENT | Chave primária |
| `nome` | VARCHAR | 255 | NOT NULL | - | Nome completo do cliente |
| `email` | VARCHAR | 255 | NOT NULL | - | Email válido do cliente |
| `documento` | VARCHAR | 20 | NULL | NULL | CPF ou CNPJ |
| `deleted_at` | TIMESTAMP | - | NULL | NULL | Timestamp de exclusão lógica |
| `created_at` | TIMESTAMP | - | NOT NULL | CURRENT_TIMESTAMP | Data de criação |
| `updated_at` | TIMESTAMP | - | NULL | NULL | Data de atualização |

**Chaves e Índices**:
- **PK**: `id` (Primary Key)
- **UK**: `email` (Unique Key) - Email deve ser único
- **IDX**: `idx_clientes_deleted_at` - Índice para exclusão lógica
- **IDX**: `idx_clientes_documento` - Índice para busca por documento (se informado)

**Constraints**:
- `email` deve ser único (exceto registros deletados logicamente)
- `nome` não pode ser vazio
- `email` deve ser válido (validação via aplicação)

---

### 2.2. Tabela: `propostas`

**Descrição**: Armazena as propostas comerciais com controle de estado e versionamento.

| Campo | Tipo | Tamanho | Null | Default | Descrição |
|-------|------|---------|------|---------|-----------|
| `id` | INT | - | NOT NULL | AUTO_INCREMENT | Chave primária |
| `cliente_id` | INT | - | NOT NULL | - | FK para clientes.id |
| `valor` | DECIMAL | 10,2 | NOT NULL | - | Valor monetário da proposta |
| `estado` | ENUM | - | NOT NULL | 'rascunho' | Estado atual (FSM) |
| `versao` | INT | - | NOT NULL | 1 | Versão para optimistic lock |
| `idempotencia_key` | VARCHAR | 255 | NULL | NULL | Chave única para idempotência |
| `enviado_em` | TIMESTAMP | - | NULL | NULL | Data/hora do envio |
| `respondido_em` | TIMESTAMP | - | NULL | NULL | Data/hora da resposta (aceita/recusada) |
| `deleted_at` | TIMESTAMP | - | NULL | NULL | Timestamp de exclusão lógica |
| `created_at` | TIMESTAMP | - | NOT NULL | CURRENT_TIMESTAMP | Data de criação |
| `updated_at` | TIMESTAMP | - | NULL | NULL | Data de atualização |

**Valores do ENUM `estado`**:
- `'rascunho'` - Estado inicial
- `'enviada'` - Proposta enviada
- `'aceita'` - Proposta aceita (final)
- `'recusada'` - Proposta recusada (final)
- `'cancelada'` - Proposta cancelada (final)

**Chaves e Índices**:
- **PK**: `id` (Primary Key)
- **FK**: `cliente_id` → `clientes.id` (ON DELETE RESTRICT)
- **UK**: `idempotencia_key` (Unique Key) - Chave de idempotência única
- **IDX**: `idx_propostas_cliente_id` - Índice para busca por cliente
- **IDX**: `idx_propostas_estado` - Índice para filtros por estado
- **IDX**: `idx_propostas_deleted_at` - Índice para exclusão lógica
- **IDX**: `idx_propostas_created_at` - Índice para ordenação temporal
- **IDX**: `idx_propostas_cliente_estado` - Índice composto (cliente_id, estado) para consultas frequentes

**Constraints**:
- `valor` deve ser > 0 (validação via aplicação)
- `versao` sempre inicia em 1 e incrementa
- `idempotencia_key` deve ser único (se informado)
- `cliente_id` deve referenciar cliente existente e não deletado

**Regras de Negócio (aplicação)**:
- Estados finais não permitem alterações
- Apenas estado RASCUNHO permite edição
- Versão é incrementada a cada alteração

---

### 2.3. Tabela: `auditoria_propostas`

**Descrição**: Registro imutável de todas as ações e alterações realizadas em propostas.

| Campo | Tipo | Tamanho | Null | Default | Descrição |
|-------|------|---------|------|---------|-----------|
| `id` | BIGINT | - | NOT NULL | AUTO_INCREMENT | Chave primária |
| `proposta_id` | INT | - | NOT NULL | - | FK para propostas.id |
| `acao` | ENUM | - | NOT NULL | - | Tipo de ação realizada |
| `estado_anterior` | ENUM | - | NULL | NULL | Estado antes da ação |
| `estado_novo` | ENUM | - | NULL | NULL | Estado após a ação |
| `dados_anteriores` | JSON | - | NULL | NULL | Snapshot dos dados antes |
| `dados_novos` | JSON | - | NULL | NULL | Snapshot dos dados após |
| `usuario` | VARCHAR | 100 | NULL | NULL | Usuário que executou a ação |
| `ip_origem` | VARCHAR | 45 | NULL | NULL | IP de origem (IPv4/IPv6) |
| `created_at` | TIMESTAMP | - | NOT NULL | CURRENT_TIMESTAMP | Data/hora do registro |

**Valores do ENUM `acao`**:
- `'CRIAR'` - Criação de nova proposta
- `'ATUALIZAR'` - Alteração de dados da proposta
- `'TRANSIÇÃO_ESTADO'` - Mudança de estado
- `'CANCELAR'` - Cancelamento da proposta

**Valores do ENUM `estado_anterior` e `estado_novo`**:
- Mesmos valores da tabela `propostas.estado`

**Chaves e Índices**:
- **PK**: `id` (Primary Key)
- **FK**: `proposta_id` → `propostas.id` (ON DELETE RESTRICT)
- **IDX**: `idx_auditoria_proposta_id` - Índice para busca por proposta
- **IDX**: `idx_auditoria_created_at` - Índice para ordenação temporal
- **IDX**: `idx_auditoria_acao` - Índice para filtros por tipo de ação
- **IDX**: `idx_auditoria_proposta_created` - Índice composto (proposta_id, created_at) para consultas históricas

**Constraints**:
- Registros são **imutáveis** (nunca atualizados ou deletados)
- `proposta_id` deve referenciar proposta existente
- `created_at` é sempre preenchido automaticamente

**Observações**:
- Tabela pode crescer muito (considerar particionamento por data em produção)
- Campos JSON permitem flexibilidade para armazenar diferentes estruturas de dados

---

## 3. Diagrama do Modelo Relacional

```
┌─────────────────────────────────────────────────────────┐
│                    CLIENTES                             │
├─────────────────────────────────────────────────────────┤
│ PK  id              INT AUTO_INCREMENT                  │
│     nome            VARCHAR(255) NOT NULL               │
│ UK  email           VARCHAR(255) NOT NULL              │
│     documento       VARCHAR(20)                        │
│     deleted_at      TIMESTAMP NULL                     │
│     created_at      TIMESTAMP NOT NULL                 │
│     updated_at      TIMESTAMP NULL                     │
└────────────────────┬────────────────────────────────────┘
                     │
                     │ 1:N
                     │
┌────────────────────▼────────────────────────────────────┐
│                    PROPOSTAS                            │
├─────────────────────────────────────────────────────────┤
│ PK  id              INT AUTO_INCREMENT                  │
│ FK  cliente_id      INT NOT NULL                       │
│     valor           DECIMAL(10,2) NOT NULL             │
│     estado          ENUM(...) NOT NULL                 │
│     versao          INT NOT NULL DEFAULT 1             │
│ UK  idempotencia_key VARCHAR(255) NULL                │
│     enviado_em      TIMESTAMP NULL                     │
│     respondido_em   TIMESTAMP NULL                     │
│     deleted_at      TIMESTAMP NULL                     │
│     created_at      TIMESTAMP NOT NULL                 │
│     updated_at      TIMESTAMP NULL                     │
└────────────────────┬────────────────────────────────────┘
                     │
                     │ 1:N
                     │
┌────────────────────▼────────────────────────────────────┐
│              AUDITORIA_PROPOSTAS                        │
├─────────────────────────────────────────────────────────┤
│ PK  id              BIGINT AUTO_INCREMENT               │
│ FK  proposta_id     INT NOT NULL                       │
│     acao            ENUM(...) NOT NULL                 │
│     estado_anterior ENUM(...) NULL                    │
│     estado_novo     ENUM(...) NULL                    │
│     dados_anteriores JSON NULL                        │
│     dados_novos     JSON NULL                         │
│     usuario         VARCHAR(100) NULL                 │
│     ip_origem       VARCHAR(45) NULL                  │
│     created_at      TIMESTAMP NOT NULL                │
└─────────────────────────────────────────────────────────┘
```

---

## 4. Exclusão Lógica (Soft Delete)

### 4.1. Estratégia Implementada

A exclusão lógica será implementada através do campo `deleted_at` (padrão NULL) nas tabelas `clientes` e `propostas`.

### 4.2. Funcionamento

#### 4.2.1. Cliente
- **Exclusão**: Ao excluir um cliente, o campo `deleted_at` é preenchido com o timestamp atual
- **Consulta**: Queries devem sempre filtrar `WHERE deleted_at IS NULL`
- **Restrição**: Não é possível excluir cliente que possui propostas ativas (não deletadas)
- **Recuperação**: Possível restaurar cliente definindo `deleted_at = NULL`

#### 4.2.2. Proposta
- **Exclusão**: Ao excluir uma proposta, o campo `deleted_at` é preenchido com o timestamp atual
- **Consulta**: Queries devem sempre filtrar `WHERE deleted_at IS NULL`
- **Restrição**: Não é possível excluir proposta que possui registros de auditoria (ou implementar cascade lógico)
- **Recuperação**: Possível restaurar proposta definindo `deleted_at = NULL`
- **Estados Finais**: Propostas em estados finais (ACEITA, RECUSADA) podem ter regras especiais de exclusão

### 4.3. Regras de Negócio

1. **Integridade Referencial com Soft Delete**:
   - Ao buscar propostas de um cliente, considerar apenas clientes não deletados
   - Ao buscar auditoria de uma proposta, considerar apenas propostas não deletadas
   - Validações devem verificar `deleted_at IS NULL` antes de criar relacionamentos

2. **Cascata Lógica**:
   - **Opção 1 (Recomendada)**: Propostas não são deletadas automaticamente quando cliente é deletado
     - Permite manter histórico mesmo se cliente for removido
   - **Opção 2**: Ao deletar cliente, deletar logicamente todas suas propostas
     - Requer validação de negócio

3. **Auditoria de Exclusão**:
   - Toda exclusão lógica deve gerar registro de auditoria
   - Ação: `'EXCLUIR'` (adicionar ao enum se necessário)
   - Registrar dados antes da exclusão

### 4.4. Queries com Soft Delete

**Exemplo - Buscar Clientes Ativos**:
```sql
SELECT * FROM clientes WHERE deleted_at IS NULL;
```

**Exemplo - Buscar Propostas de Cliente Ativo**:
```sql
SELECT p.* 
FROM propostas p
INNER JOIN clientes c ON p.cliente_id = c.id
WHERE p.deleted_at IS NULL 
  AND c.deleted_at IS NULL
  AND c.id = ?;
```

**Exemplo - Exclusão Lógica de Proposta**:
```sql
UPDATE propostas 
SET deleted_at = CURRENT_TIMESTAMP, 
    updated_at = CURRENT_TIMESTAMP
WHERE id = ? AND deleted_at IS NULL;
```

### 4.5. Índices para Performance

- `idx_clientes_deleted_at` - Acelera filtros de exclusão lógica
- `idx_propostas_deleted_at` - Acelera filtros de exclusão lógica
- Considerar índices compostos incluindo `deleted_at` em consultas frequentes

---

## 5. Persistência da Auditoria

### 5.1. Estratégia de Persistência

A auditoria será persistida de forma **síncrona e transacional** para garantir integridade e rastreabilidade completa.

### 5.2. Quando Registrar Auditoria

A auditoria é registrada automaticamente em **todas** as operações que alteram dados:

1. **CRIAR Proposta**:
   - Ação: `'CRIAR'`
   - `estado_anterior`: NULL
   - `estado_novo`: `'rascunho'`
   - `dados_anteriores`: `{}` (vazio)
   - `dados_novos`: Snapshot completo da proposta criada

2. **ATUALIZAR Proposta**:
   - Ação: `'ATUALIZAR'`
   - `estado_anterior`: Estado atual (não muda)
   - `estado_novo`: Estado atual (não muda)
   - `dados_anteriores`: Snapshot dos dados antes da alteração
   - `dados_novos`: Snapshot dos dados após a alteração

3. **TRANSIÇÃO_ESTADO**:
   - Ação: `'TRANSIÇÃO_ESTADO'`
   - `estado_anterior`: Estado antes da transição
   - `estado_novo`: Estado após a transição
   - `dados_anteriores`: Snapshot antes (incluindo estado anterior)
   - `dados_novos`: Snapshot após (incluindo estado novo)

4. **CANCELAR Proposta**:
   - Ação: `'CANCELAR'`
   - `estado_anterior`: Estado atual
   - `estado_novo`: `'cancelada'`
   - `dados_anteriores`: Snapshot antes
   - `dados_novos`: Snapshot após cancelamento

### 5.3. Estrutura dos Dados JSON

#### 5.3.1. Dados Anteriores/Novos (Exemplo)
```json
{
  "id": 1,
  "cliente_id": 5,
  "valor": 1500.00,
  "estado": "rascunho",
  "versao": 1,
  "idempotencia_key": "key-123",
  "enviado_em": null,
  "respondido_em": null
}
```

#### 5.3.2. Dados Parciais (Apenas Campos Alterados)
Para otimização, pode-se armazenar apenas campos alterados:
```json
{
  "valor": 2000.00,
  "versao": 2
}
```

### 5.4. Fluxo de Persistência

```
┌─────────────────────────────────────────────────────────┐
│ 1. Operação Iniciada (ex: Atualizar Proposta)          │
└────────────────────┬────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────┐
│ 2. Capturar Snapshot dos Dados Atuais                   │
│    (dados_anteriores)                                    │
└────────────────────┬────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────┐
│ 3. Executar Operação no Banco                           │
│    (UPDATE propostas SET ...)                           │
└────────────────────┬────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────┐
│ 4. Capturar Snapshot dos Dados Após Alteração           │
│    (dados_novos)                                        │
└────────────────────┬────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────┐
│ 5. Inserir Registro de Auditoria                        │
│    (INSERT INTO auditoria_propostas ...)                │
│    - Dentro da mesma transação                          │
└────────────────────┬────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────┐
│ 6. Commit da Transação                                   │
│    (Garante atomicidade)                                │
└─────────────────────────────────────────────────────────┘
```

### 5.5. Transação e Atomicidade

**Regra Crítica**: A inserção de auditoria deve ocorrer **dentro da mesma transação** da operação principal.

**Exemplo de Transação**:
```sql
BEGIN TRANSACTION;

-- 1. Atualizar proposta
UPDATE propostas 
SET valor = 2000.00, 
    versao = versao + 1,
    updated_at = CURRENT_TIMESTAMP
WHERE id = 1 AND versao = 1;

-- 2. Registrar auditoria (mesma transação)
INSERT INTO auditoria_propostas (
    proposta_id, acao, estado_anterior, estado_novo,
    dados_anteriores, dados_novos, usuario, created_at
) VALUES (
    1, 'ATUALIZAR', 'rascunho', 'rascunho',
    '{"valor": 1500.00, "versao": 1}',
    '{"valor": 2000.00, "versao": 2}',
    'admin',
    CURRENT_TIMESTAMP
);

COMMIT;
```

### 5.6. Metadados Adicionais

Cada registro de auditoria armazena:
- **usuario**: Identificação do usuário que executou a ação
- **ip_origem**: IP de origem da requisição (para segurança)
- **created_at**: Timestamp exato da ação (não confundir com updated_at da proposta)

### 5.7. Imutabilidade da Auditoria

**Regra**: Registros de auditoria são **imutáveis**:
- ❌ Nunca são atualizados
- ❌ Nunca são deletados (nem soft delete)
- ✅ Apenas inserção de novos registros

### 5.8. Performance e Escalabilidade

**Considerações**:
1. **Índices**: Índices em `proposta_id` e `created_at` para consultas rápidas
2. **Particionamento**: Em produção, considerar particionamento por data (`created_at`)
3. **Arquivamento**: Política de arquivamento de registros antigos (se necessário)
4. **Volume**: Tabela pode crescer muito - monitorar tamanho

**Exemplo de Consulta Otimizada**:
```sql
SELECT * 
FROM auditoria_propostas
WHERE proposta_id = ?
  AND created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)
ORDER BY created_at DESC;
```

### 5.9. Integridade Referencial

- **FK**: `proposta_id` referencia `propostas.id`
- **ON DELETE RESTRICT**: Não permite deletar proposta com auditoria
- **Com Soft Delete**: Considerar apenas propostas não deletadas nas consultas

---

## 6. Resumo do Modelo

### 6.1. Tabelas
- **3 tabelas principais**: `clientes`, `propostas`, `auditoria_propostas`

### 6.2. Chaves
- **3 PKs**: Uma por tabela (id)
- **2 FKs**: `propostas.cliente_id` → `clientes.id`, `auditoria_propostas.proposta_id` → `propostas.id`
- **2 UKs**: `clientes.email`, `propostas.idempotencia_key`

### 6.3. Índices
- **11 índices** distribuídos para otimizar consultas frequentes
- Índices compostos para consultas complexas
- Índices para soft delete

### 6.4. Exclusão Lógica
- Implementada via campo `deleted_at` (NULL = ativo)
- Aplicada em `clientes` e `propostas`
- `auditoria_propostas` é imutável (sem soft delete)

### 6.5. Auditoria
- Persistência síncrona e transacional
- Registro automático de todas as alterações
- Dados JSON para flexibilidade
- Imutável e rastreável

---

## 7. Observações Finais

1. **Tipos de Dados**:
   - `DECIMAL(10,2)` para valores monetários (precisão)
   - `BIGINT` para auditoria (pode crescer muito)
   - `ENUM` para estados (validação no banco)
   - `JSON` para dados flexíveis na auditoria

2. **Timestamps**:
   - `created_at` sempre preenchido
   - `updated_at` atualizado em modificações
   - `deleted_at` para soft delete

3. **Versionamento**:
   - Campo `versao` para optimistic lock
   - Incrementa a cada alteração
   - Verificado antes de atualizações

4. **Idempotência**:
   - Campo único `idempotencia_key`
   - Permite requisições duplicadas sem efeitos colaterais

5. **Escalabilidade**:
   - Índices otimizados
   - Considerar particionamento de auditoria
   - Monitorar crescimento das tabelas
