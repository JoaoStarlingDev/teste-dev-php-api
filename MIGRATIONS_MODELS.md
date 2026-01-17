# Migrations e Models - CodeIgniter 4

## Resumo

Foram criadas **3 migrations** e **3 models** seguindo o padrão CodeIgniter 4, implementando o modelo relacional definido anteriormente.

---

## 1. Migrations

### 1.1. CreateClientesTable

**Arquivo**: `app/Database/Migrations/2024-01-15-100000_CreateClientesTable.php`

**Objetivo**: Criar tabela de clientes com soft delete e índices otimizados.

**Campos**:
- `id` (PK, AUTO_INCREMENT)
- `nome` (VARCHAR 255, NOT NULL)
- `email` (VARCHAR 255, NOT NULL, UNIQUE)
- `documento` (VARCHAR 20, NULL)
- `deleted_at` (TIMESTAMP NULL) - Soft delete
- `created_at` (TIMESTAMP, NOT NULL)
- `updated_at` (TIMESTAMP NULL)

**Índices**:
- Primary Key: `id`
- Unique Key: `email`
- Index: `deleted_at` (para filtros de soft delete)
- Index: `documento` (para buscas por documento)

**Características**:
- Soft delete implementado via `deleted_at`
- Email único (permite múltiplos NULL em soft delete)
- Timestamps automáticos

---

### 1.2. CreatePropostasTable

**Arquivo**: `app/Database/Migrations/2024-01-15-100100_CreatePropostasTable.php`

**Objetivo**: Criar tabela de propostas com relacionamento, estados, versionamento e idempotência.

**Campos**:
- `id` (PK, AUTO_INCREMENT)
- `cliente_id` (FK → clientes.id, NOT NULL)
- `valor` (DECIMAL 10,2, NOT NULL)
- `estado` (ENUM: rascunho, enviada, aceita, recusada, cancelada, DEFAULT: rascunho)
- `versao` (INT, DEFAULT 1, NOT NULL) - Optimistic lock
- `idempotencia_key` (VARCHAR 255, NULL, UNIQUE)
- `enviado_em` (TIMESTAMP NULL)
- `respondido_em` (TIMESTAMP NULL)
- `deleted_at` (TIMESTAMP NULL) - Soft delete
- `created_at` (TIMESTAMP, NOT NULL)
- `updated_at` (TIMESTAMP NULL)

**Constraints**:
- Foreign Key: `cliente_id` → `clientes.id` (ON DELETE RESTRICT)
- Unique Key: `idempotencia_key` (permite múltiplos NULL)

**Índices**:
- Primary Key: `id`
- Foreign Key Index: `cliente_id`
- Index: `estado` (para filtros por estado)
- Index: `deleted_at` (soft delete)
- Index: `created_at` (ordenação temporal)
- Composite Index: `[cliente_id, estado]` (consultas frequentes)

**Características**:
- Estados validados via ENUM
- Versionamento para optimistic lock
- Idempotência via chave única
- Timestamps de controle (enviado_em, respondido_em)

---

### 1.3. CreatePropostaAuditoriaTable

**Arquivo**: `app/Database/Migrations/2024-01-15-100200_CreatePropostaAuditoriaTable.php`

**Objetivo**: Criar tabela imutável de auditoria com histórico completo de alterações.

**Campos**:
- `id` (BIGINT PK, AUTO_INCREMENT)
- `proposta_id` (FK → propostas.id, NOT NULL)
- `acao` (ENUM: CRIAR, ATUALIZAR, TRANSIÇÃO_ESTADO, CANCELAR, NOT NULL)
- `estado_anterior` (ENUM: estados da proposta, NULL)
- `estado_novo` (ENUM: estados da proposta, NULL)
- `dados_anteriores` (JSON, NULL)
- `dados_novos` (JSON, NULL)
- `usuario` (VARCHAR 100, NULL)
- `ip_origem` (VARCHAR 45, NULL) - Suporta IPv4 e IPv6
- `created_at` (TIMESTAMP, NOT NULL)

**Constraints**:
- Foreign Key: `proposta_id` → `propostas.id` (ON DELETE RESTRICT)
- Sem `updated_at` ou `deleted_at` (tabela imutável)

**Índices**:
- Primary Key: `id`
- Foreign Key Index: `proposta_id`
- Index: `created_at` (ordenação temporal)
- Index: `acao` (filtros por tipo de ação)
- Composite Index: `[proposta_id, created_at]` (consultas históricas)

**Características**:
- Tabela imutável (sem updates/deletes)
- Dados JSON para flexibilidade
- Rastreamento completo (usuario, ip_origem)
- BIGINT para suportar alto volume

---

## 2. Models

### 2.1. ClienteModel

**Arquivo**: `app/Models/ClienteModel.php`

**Funcionalidades**:
- ✅ Soft delete habilitado
- ✅ Timestamps automáticos
- ✅ Validação de campos (nome, email, documento)
- ✅ Métodos auxiliares:
  - `buscarPorEmail()` - Busca por email
  - `buscarPorDocumento()` - Busca por documento
  - `existe()` - Verifica existência

**Validações**:
- Nome: obrigatório, mínimo 3 caracteres
- Email: obrigatório, formato válido
- Documento: opcional, máximo 20 caracteres

**Uso**:
```php
$clienteModel = new ClienteModel();

// Criar
$clienteModel->insert([
    'nome' => 'João Silva',
    'email' => 'joao@example.com',
    'documento' => '123.456.789-00'
]);

// Buscar por email
$cliente = $clienteModel->buscarPorEmail('joao@example.com');
```

---

### 2.2. PropostaModel

**Arquivo**: `app/Models/PropostaModel.php`

**Funcionalidades**:
- ✅ Soft delete habilitado
- ✅ Timestamps automáticos
- ✅ Validação de campos e estados
- ✅ Callbacks automáticos:
  - `setVersaoInicial()` - Define versão = 1 ao criar
  - `incrementarVersao()` - Incrementa versão ao atualizar
- ✅ Métodos auxiliares:
  - `buscarPorIdComVersao()` - Busca com verificação de versão (optimistic lock)
  - `buscarPorIdempotenciaKey()` - Busca por chave de idempotência
  - `listar()` - Lista com paginação
  - `listarPorCliente()` - Lista por cliente
  - `listarPorEstado()` - Lista por estado
  - `transicionarEstado()` - Transiciona estado e define timestamps
  - `podeSerEditada()` - Verifica se pode editar (apenas RASCUNHO)

**Validações**:
- Cliente ID: obrigatório, inteiro
- Valor: obrigatório, decimal, maior que zero
- Estado: obrigatório, valores válidos do ENUM
- Versão: obrigatório, inteiro, maior que zero

**Uso**:
```php
$propostaModel = new PropostaModel();

// Criar
$propostaModel->insert([
    'cliente_id' => 1,
    'valor' => 1500.00,
    'estado' => 'rascunho',
    'idempotencia_key' => 'key-123'
]);

// Buscar com verificação de versão (optimistic lock)
$proposta = $propostaModel->buscarPorIdComVersao(1, 2);

// Transicionar estado
$propostaModel->transicionarEstado(1, 'enviada', 2);
```

---

### 2.3. PropostaAuditoriaModel

**Arquivo**: `app/Models/PropostaAuditoriaModel.php`

**Funcionalidades**:
- ✅ Soft delete **desabilitado** (tabela imutável)
- ✅ Timestamps (apenas created_at)
- ✅ Validação de campos e ações
- ✅ Callback automático:
  - `validarDadosJSON()` - Converte arrays para JSON
- ✅ Métodos auxiliares:
  - `buscarPorProposta()` - Busca histórico de uma proposta
  - `buscarPorAcao()` - Busca por tipo de ação
  - `buscarPorPropostaEAcao()` - Busca combinada
  - `buscarPorPeriodo()` - Busca por período
  - `registrarCriacao()` - Registra criação
  - `registrarAtualizacao()` - Registra atualização
  - `registrarTransicaoEstado()` - Registra transição de estado

**Validações**:
- Proposta ID: obrigatório, inteiro
- Ação: obrigatório, valores válidos do ENUM
- Estados: opcionais, valores válidos do ENUM

**Uso**:
```php
$auditoriaModel = new PropostaAuditoriaModel();

// Registrar criação
$auditoriaModel->registrarCriacao(1, [
    'valor' => 1500.00,
    'estado' => 'rascunho'
], 'admin', '192.168.1.1');

// Registrar transição de estado
$auditoriaModel->registrarTransicaoEstado(
    1, 
    'rascunho', 
    'enviada',
    ['estado' => 'rascunho'],
    ['estado' => 'enviada'],
    'admin',
    '192.168.1.1'
);

// Buscar histórico
$historico = $auditoriaModel->buscarPorProposta(1);
```

---

## 3. Ordem de Execução das Migrations

As migrations devem ser executadas na seguinte ordem:

1. **CreateClientesTable** (primeira)
2. **CreatePropostasTable** (segunda - depende de clientes)
3. **CreatePropostaAuditoriaTable** (terceira - depende de propostas)

A nomenclatura com timestamp garante a ordem correta:
- `2024-01-15-100000` - Clientes
- `2024-01-15-100100` - Propostas
- `2024-01-15-100200` - Auditoria

---

## 4. Comandos CodeIgniter 4

### Executar Migrations

```bash
# Executar todas as migrations
php spark migrate

# Executar migrations específicas
php spark migrate -g default

# Reverter última migration
php spark migrate:rollback

# Reverter todas as migrations
php spark migrate:rollback -all
```

### Verificar Status

```bash
# Ver status das migrations
php spark migrate:status
```

---

## 5. Características Implementadas

### 5.1. Soft Delete
- ✅ Implementado em `clientes` e `propostas`
- ✅ Campo `deleted_at` (TIMESTAMP NULL)
- ✅ Models configurados com `useSoftDeletes = true`
- ✅ Índices para performance em consultas

### 5.2. Optimistic Lock
- ✅ Campo `versao` em propostas
- ✅ Incremento automático via callback
- ✅ Método `buscarPorIdComVersao()` para validação

### 5.3. Idempotência
- ✅ Campo `idempotencia_key` único
- ✅ Permite múltiplos NULL (soft delete)
- ✅ Método `buscarPorIdempotenciaKey()` no model

### 5.4. Auditoria
- ✅ Tabela imutável (sem soft delete)
- ✅ Dados JSON para flexibilidade
- ✅ Métodos auxiliares para registro
- ✅ Rastreamento completo (usuario, ip_origem)

### 5.5. Relacionamentos
- ✅ Foreign Keys com RESTRICT
- ✅ Integridade referencial garantida
- ✅ Índices em FKs para performance

---

## 6. Próximos Passos

Com as migrations e models criados, você pode:

1. Executar as migrations para criar as tabelas
2. Integrar os models com os repositories da camada Infrastructure
3. Implementar os services da camada Application
4. Conectar com os controllers da camada Presentation

Os models do CodeIgniter 4 podem ser usados diretamente nos repositories ou adaptados para trabalhar com as entidades de domínio.
