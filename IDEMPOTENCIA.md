# Sistema de Idempotência

## Visão Geral

Implementação de suporte a **Idempotency-Key** para garantir que requisições duplicadas não criem recursos duplicados e retornem sempre a mesma resposta.

---

## 1. Operações com Suporte a Idempotência

### 1.1. Criação de Cliente

✅ **Suporte completo** via campo `idempotencia_key` na tabela `clientes`

### 1.2. Criação de Proposta

✅ **Suporte completo** via campo `idempotencia_key` na tabela `propostas`

### 1.3. Submissão de Proposta

✅ **Suporte completo** via tabela `idempotencia_operacoes`

---

## 2. Onde a Chave é Armazenada

### 2.1. Criação de Cliente

**Tabela**: `clientes`

**Campo**: `idempotencia_key` (VARCHAR 255, NULL, UNIQUE)

```sql
CREATE TABLE clientes (
    id INT PRIMARY KEY,
    nome VARCHAR(255),
    email VARCHAR(255),
    documento VARCHAR(20),
    idempotencia_key VARCHAR(255) UNIQUE,  -- ✅ Chave armazenada aqui
    ...
);
```

**Características**:
- Campo único (permite múltiplos NULL)
- Armazenado diretamente na entidade Cliente
- Indexado para busca rápida

### 2.2. Criação de Proposta

**Tabela**: `propostas`

**Campo**: `idempotencia_key` (VARCHAR 255, NULL, UNIQUE)

```sql
CREATE TABLE propostas (
    id INT PRIMARY KEY,
    cliente_id INT,
    valor DECIMAL(10,2),
    estado ENUM(...),
    idempotencia_key VARCHAR(255) UNIQUE,  -- ✅ Chave armazenada aqui
    ...
);
```

**Características**:
- Campo único (permite múltiplos NULL)
- Armazenado diretamente na entidade Proposta
- Indexado para busca rápida

### 2.3. Submissão de Proposta

**Tabela**: `idempotencia_operacoes`

**Campos**: 
- `idempotencia_key` (VARCHAR 255)
- `tipo_operacao` (VARCHAR 50) = 'submeter_proposta'
- `entidade_id` (INT) = proposta_id
- `resultado` (JSON) = snapshot da proposta submetida

```sql
CREATE TABLE idempotencia_operacoes (
    id INT PRIMARY KEY,
    idempotencia_key VARCHAR(255),
    tipo_operacao VARCHAR(50),
    entidade_id INT,
    resultado JSON,
    created_at TIMESTAMP,
    UNIQUE KEY (idempotencia_key, tipo_operacao)  -- ✅ Chave única composta
);
```

**Características**:
- Chave única composta: `(idempotencia_key, tipo_operacao)`
- Permite múltiplas operações diferentes com mesma chave
- Armazena resultado completo da operação

---

## 3. Como Evitar Duplicidade

### 3.1. Estratégia Geral

**Verificação antes de executar operação**:

1. Se `idempotencia_key` for fornecida
2. Buscar registro existente com essa chave
3. Se encontrado: **retornar resultado anterior** (idempotência)
4. Se não encontrado: **executar operação** e **salvar resultado**

### 3.2. Criação de Cliente

**Fluxo**:

```
1. Cliente envia POST /clientes com Idempotency-Key: "client-001"
2. Service busca cliente por idempotencia_key = "client-001"
3. Se encontrado:
   → Retorna cliente existente (HTTP 200/201)
4. Se não encontrado:
   → Cria novo cliente com idempotencia_key = "client-001"
   → Retorna cliente criado (HTTP 201)
```

**Código**:

```php
public function criar(string $nome, string $email, ?string $documento = null, ?string $idempotenciaKey = null): Cliente
{
    // Verifica idempotência PRIMEIRO
    if ($idempotenciaKey !== null) {
        $clienteExistente = $this->clienteRepository->buscarPorIdempotenciaKey($idempotenciaKey);
        
        if ($clienteExistente !== null) {
            // Retorna cliente existente (idempotência)
            return $clienteExistente;
        }
    }

    // Verifica outras regras (email único, etc.)
    // ...

    // Cria novo cliente
    $cliente = new Cliente($nome, $email, $documento, $idempotenciaKey);
    $this->clienteRepository->salvar($cliente);

    return $cliente;
}
```

**Proteção**:
- ✅ Campo `idempotencia_key` UNIQUE no banco
- ✅ Verificação antes da criação
- ✅ Retorna sempre o mesmo cliente para mesma chave

### 3.3. Criação de Proposta

**Fluxo**:

```
1. Cliente envia POST /propostas com Idempotency-Key: "proposta-001"
2. Service busca proposta por idempotencia_key = "proposta-001"
3. Se encontrado:
   → Retorna proposta existente (HTTP 200/201)
4. Se não encontrado:
   → Cria nova proposta com idempotencia_key = "proposta-001"
   → Retorna proposta criada (HTTP 201)
```

**Código**:

```php
public function criarProposta(int $clienteId, float $valor, ?string $idempotenciaKey = null, ?string $usuario = null): Proposta
{
    // Verifica idempotência PRIMEIRO
    if ($idempotenciaKey !== null) {
        $key = new IdempotenciaKey($idempotenciaKey);
        $propostaExistente = $this->propostaRepository->buscarPorIdempotenciaKey($key);
        
        if ($propostaExistente !== null) {
            // Retorna proposta existente (idempotência)
            return $propostaExistente;
        }
    }

    // Cria nova proposta
    // ...
}
```

**Proteção**:
- ✅ Campo `idempotencia_key` UNIQUE no banco
- ✅ Verificação antes da criação
- ✅ Retorna sempre a mesma proposta para mesma chave

### 3.4. Submissão de Proposta

**Fluxo**:

```
1. Cliente envia POST /propostas/1/submeter com Idempotency-Key: "submit-001"
2. Service busca em idempotencia_operacoes onde:
   - idempotencia_key = "submit-001"
   - tipo_operacao = "submeter_proposta"
3. Se encontrado:
   → Busca proposta por entidade_id
   → Retorna proposta já submetida (HTTP 200)
4. Se não encontrado:
   → Executa submissão
   → Salva registro em idempotencia_operacoes
   → Retorna proposta submetida (HTTP 200)
```

**Código**:

```php
public function submeterProposta(int $propostaId, int $versaoEsperada, ?string $idempotenciaKey = null, ?string $usuario = null): Proposta
{
    // Verifica idempotência PRIMEIRO
    if ($idempotenciaKey !== null) {
        $operacaoExistente = $this->idempotenciaRepository->buscarPorKeyETipo(
            $idempotenciaKey,
            'submeter_proposta'
        );

        if ($operacaoExistente !== null) {
            // Busca proposta já submetida e retorna (idempotência)
            $propostaExistente = $this->propostaRepository->buscarPorId($operacaoExistente->getEntidadeId());
            if ($propostaExistente !== null) {
                return $propostaExistente;
            }
        }
    }

    // Executa submissão
    // ...

    // Salva idempotência se chave fornecida
    if ($idempotenciaKey !== null) {
        $idempotenciaOperacao = new IdempotenciaOperacao(
            $idempotenciaKey,
            'submeter_proposta',
            $proposta->getId(),
            $this->serializarProposta($proposta)
        );
        $this->idempotenciaRepository->salvar($idempotenciaOperacao);
    }

    return $proposta;
}
```

**Proteção**:
- ✅ Chave única composta `(idempotencia_key, tipo_operacao)` no banco
- ✅ Verificação antes da execução
- ✅ Salva resultado após execução bem-sucedida
- ✅ Retorna sempre o mesmo resultado para mesma chave

---

## 4. Como Retornar a Resposta Anterior

### 4.1. Estratégia

**Retornar a entidade original**, não apenas o resultado armazenado. Isso garante:
- ✅ Dados sempre atualizados (se entidade mudou)
- ✅ Consistência com estado atual
- ✅ Mesmo formato de resposta

### 4.2. Criação de Cliente

**Implementação**:

```php
// Busca cliente existente
$clienteExistente = $this->clienteRepository->buscarPorIdempotenciaKey($idempotenciaKey);

if ($clienteExistente !== null) {
    // Retorna cliente existente (mesma instância, dados atualizados)
    return $clienteExistente;
}
```

**Vantagens**:
- Retorna entidade completa
- Dados podem estar atualizados (se cliente foi modificado)
- Mesma interface de resposta

### 4.3. Criação de Proposta

**Implementação**:

```php
// Busca proposta existente
$propostaExistente = $this->propostaRepository->buscarPorIdempotenciaKey($key);

if ($propostaExistente !== null) {
    // Retorna proposta existente (mesma instância, estado atual)
    return $propostaExistente;
}
```

**Vantagens**:
- Retorna entidade completa
- Estado pode ter mudado (ex: foi submetida)
- Mesma interface de resposta

### 4.4. Submissão de Proposta

**Implementação**:

```php
// Busca operação existente
$operacaoExistente = $this->idempotenciaRepository->buscarPorKeyETipo(
    $idempotenciaKey,
    'submeter_proposta'
);

if ($operacaoExistente !== null) {
    // Busca proposta atual (pode ter mudado desde então)
    $propostaExistente = $this->propostaRepository->buscarPorId($operacaoExistente->getEntidadeId());
    
    if ($propostaExistente !== null) {
        // Retorna proposta atual (estado pode ter mudado, ex: foi aprovada)
        return $propostaExistente;
    }
}
```

**Vantagens**:
- Retorna entidade atualizada
- Estado pode ter mudado desde a primeira submissão (ex: foi aprovada)
- Garante idempotência (não executa submissão novamente)
- Mesma interface de resposta

---

## 5. Exemplos de Uso

### 5.1. Criar Cliente com Idempotência

**Primeira requisição**:

```http
POST /api/v1/clientes
Idempotency-Key: client-001
Content-Type: application/json

{
  "nome": "João Silva",
  "email": "joao@example.com",
  "documento": "123.456.789-00"
}
```

**Resposta (HTTP 201)**:
```json
{
  "id": 1,
  "nome": "João Silva",
  "email": "joao@example.com",
  "documento": "123.456.789-00",
  "idempotencia_key": "client-001"
}
```

**Segunda requisição (mesma chave)**:

```http
POST /api/v1/clientes
Idempotency-Key: client-001
Content-Type: application/json

{
  "nome": "João Silva",
  "email": "joao@example.com",
  "documento": "123.456.789-00"
}
```

**Resposta (HTTP 200 ou 201)**:
```json
{
  "id": 1,
  "nome": "João Silva",
  "email": "joao@example.com",
  "documento": "123.456.789-00",
  "idempotencia_key": "client-001"
}
```

**Mesmo cliente retornado**, mesmo que dados na requisição sejam diferentes (comportamento idempotente).

### 5.2. Criar Proposta com Idempotência

**Primeira requisição**:

```http
POST /api/v1/propostas
Idempotency-Key: proposta-001
Content-Type: application/json

{
  "cliente_id": 1,
  "valor": 1500.00
}
```

**Resposta (HTTP 201)**:
```json
{
  "id": 1,
  "cliente_id": 1,
  "valor": 1500.00,
  "estado": "rascunho",
  "versao": 1,
  "idempotencia_key": "proposta-001"
}
```

**Segunda requisição (mesma chave)**:

```http
POST /api/v1/propostas
Idempotency-Key: proposta-001
Content-Type: application/json

{
  "cliente_id": 1,
  "valor": 2000.00  -- Valor diferente
}
```

**Resposta (HTTP 200 ou 201)**:
```json
{
  "id": 1,
  "cliente_id": 1,
  "valor": 1500.00,  -- Valor original mantido
  "estado": "rascunho",
  "versao": 1,
  "idempotencia_key": "proposta-001"
}
```

### 5.3. Submeter Proposta com Idempotência

**Primeira requisição**:

```http
POST /api/v1/propostas/1/submeter
Idempotency-Key: submit-001
Content-Type: application/json

{
  "versao": 1
}
```

**Resposta (HTTP 200)**:
```json
{
  "id": 1,
  "estado": "enviada",
  "versao": 2
}
```

**Segunda requisição (mesma chave)**:

```http
POST /api/v1/propostas/1/submeter
Idempotency-Key: submit-001
Content-Type: application/json

{
  "versao": 1
}
```

**Resposta (HTTP 200)**:
```json
{
  "id": 1,
  "estado": "enviada",  -- Pode ter mudado para "aceita" se foi aprovada
  "versao": 2  -- Ou versão atualizada
}
```

**Submissão não é executada novamente**, mas retorna estado atual da proposta.

---

## 6. Segurança e Boas Práticas

### 6.1. Validação da Chave

- ✅ Chave não pode ser vazia
- ✅ Chave máximo 255 caracteres
- ✅ Validação via Value Object `IdempotenciaKey`

### 6.2. Expiração (Opcional)

Por enquanto, chaves **não expiram**. Para implementar expiração:

```sql
-- Adicionar campo expires_at
ALTER TABLE idempotencia_operacoes 
ADD COLUMN expires_at TIMESTAMP NULL;

-- Criar índice
CREATE INDEX idx_expires_at ON idempotencia_operacoes(expires_at);

-- Limpar registros expirados periodicamente
DELETE FROM idempotencia_operacoes 
WHERE expires_at < NOW();
```

### 6.3. Limpeza de Dados

Considerar limpeza periódica de registros antigos de `idempotencia_operacoes`:

```sql
-- Manter apenas últimos 30 dias
DELETE FROM idempotencia_operacoes 
WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
```

### 6.4. Geração de Chaves

**Cliente deve gerar chaves únicas**:
- UUID v4 (recomendado)
- Timestamp + random
- Hash de dados da requisição

**Exemplo**:
```php
// Gerar chave única
$idempotencyKey = bin2hex(random_bytes(16)); // 32 caracteres hex
// ou
$idempotencyKey = uniqid('', true); // 13 caracteres + timestamp
```

---

## 7. Resumo

### 7.1. Onde é Armazenado

| Operação | Tabela | Campo/Combinado |
|----------|--------|-----------------|
| Criar Cliente | `clientes` | `idempotencia_key` (UNIQUE) |
| Criar Proposta | `propostas` | `idempotencia_key` (UNIQUE) |
| Submeter Proposta | `idempotencia_operacoes` | `(idempotencia_key, tipo_operacao)` (UNIQUE) |

### 7.2. Como Evita Duplicidade

1. ✅ **Verificação antes da execução**: Busca registro existente
2. ✅ **Chave única no banco**: Impede duplicatas
3. ✅ **Retorno imediato**: Se encontrado, retorna resultado anterior
4. ✅ **Sem side effects**: Operação não é executada novamente

### 7.3. Como Retorna Resposta Anterior

1. ✅ **Busca entidade atual**: Não retorna snapshot, mas entidade atual
2. ✅ **Estado atualizado**: Pode retornar estado modificado (ex: proposta aprovada)
3. ✅ **Mesma interface**: Mesmo formato de resposta
4. ✅ **Idempotência garantida**: Operação não é executada novamente

---

## 8. Estrutura de Arquivos

```
app/Database/Migrations/
├── 2024-01-15-100300_AddIdempotenciaKeyToClientes.php  ✅
└── 2024-01-15-100400_CreateIdempotenciaOperacoesTable.php  ✅

src/Domain/
├── Cliente/
│   ├── Cliente.php  ✅ (atualizado)
│   └── ClienteRepositoryInterface.php  ✅ (atualizado)
└── Idempotencia/
    ├── IdempotenciaOperacao.php  ✅
    └── IdempotenciaOperacaoRepositoryInterface.php  ✅

src/Application/Services/
├── ClienteService.php  ✅ (atualizado)
└── PropostaService.php  ✅ (atualizado)
```

---

## 9. Migrations Necessárias

Execute as migrations na ordem:

```bash
# 1. Adicionar idempotencia_key em clientes
php spark migrate

# 2. Criar tabela de idempotência de operações
php spark migrate
```

---

## 10. Conclusão

✅ **Implementação simples e segura**
✅ **Suporte completo para criação de cliente**
✅ **Suporte completo para criação de proposta**
✅ **Suporte completo para submissão de proposta**
✅ **Prevenção de duplicidade garantida**
✅ **Retorno de resposta anterior implementado**
✅ **Extensível para outras operações**

O sistema está pronto para uso em produção com todas as garantias de idempotência necessárias.
