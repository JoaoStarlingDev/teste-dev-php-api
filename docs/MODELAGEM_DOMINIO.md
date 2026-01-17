# Modelagem do Domínio - Sistema de Gestão de Propostas

## 1. Entidades Identificadas

### 1.1. Cliente
**Descrição**: Representa o cliente que receberá a proposta.

### 1.2. Proposta
**Descrição**: Representa uma proposta comercial enviada a um cliente.

### 1.3. Auditoria de Proposta
**Descrição**: Registro histórico de todas as alterações e ações realizadas em propostas.

---

## 2. Atributos das Entidades

### 2.1. Cliente

| Atributo | Tipo | Obrigatório | Descrição |
|----------|------|-------------|-----------|
| `id` | Integer | Sim (PK) | Identificador único do cliente |
| `nome` | String | Sim | Nome completo do cliente |
| `email` | String | Sim | Email válido do cliente |
| `documento` | String | Não | CPF/CNPJ do cliente |
| `criado_em` | DateTime | Sim | Data/hora de criação do registro |
| `atualizado_em` | DateTime | Não | Data/hora da última atualização |

**Validações**:
- Nome: não pode ser vazio, mínimo 3 caracteres
- Email: deve ser um email válido
- Documento: formato válido (CPF ou CNPJ) se informado

---

### 2.2. Proposta

| Atributo | Tipo | Obrigatório | Descrição |
|----------|------|-------------|-----------|
| `id` | Integer | Sim (PK) | Identificador único da proposta |
| `cliente_id` | Integer | Sim (FK) | Referência ao cliente |
| `valor` | Decimal(10,2) | Sim | Valor monetário da proposta |
| `estado` | Enum | Sim | Estado atual da proposta (ver FSM) |
| `versao` | Integer | Sim | Versão para controle de concorrência (optimistic lock) |
| `idempotencia_key` | String(255) | Não | Chave única para garantir idempotência |
| `criado_em` | DateTime | Sim | Data/hora de criação |
| `atualizado_em` | DateTime | Não | Data/hora da última atualização |
| `enviado_em` | DateTime | Não | Data/hora em que foi enviada |
| `respondido_em` | DateTime | Não | Data/hora em que foi aceita/recusada |

**Validações**:
- Valor: deve ser maior que zero
- Estado: deve ser um estado válido da FSM
- Versão: sempre inicia em 1, incrementa a cada alteração
- Idempotência Key: deve ser única no sistema

**Regras de Negócio**:
- Apenas propostas em RASCUNHO podem ser editadas
- Estados finais não permitem alterações
- Versão é incrementada automaticamente a cada modificação

---

### 2.3. Auditoria de Proposta

| Atributo | Tipo | Obrigatório | Descrição |
|----------|------|-------------|-----------|
| `id` | Integer | Sim (PK) | Identificador único do registro |
| `proposta_id` | Integer | Sim (FK) | Referência à proposta auditada |
| `acao` | Enum | Sim | Tipo de ação realizada (CRIAR, ATUALIZAR, TRANSIÇÃO_ESTADO, CANCELAR) |
| `estado_anterior` | Enum | Não | Estado da proposta antes da ação |
| `estado_novo` | Enum | Não | Estado da proposta após a ação |
| `dados_anteriores` | JSON | Não | Snapshot dos dados antes da alteração |
| `dados_novos` | JSON | Não | Snapshot dos dados após a alteração |
| `usuario` | String | Não | Identificação do usuário que executou a ação |
| `ip_origem` | String | Não | IP de origem da requisição |
| `ocorrido_em` | DateTime | Sim | Data/hora em que a ação foi registrada |

**Validações**:
- Proposta ID: deve referenciar uma proposta existente
- Ação: deve ser um tipo válido
- Ocorrido Em: sempre preenchido automaticamente

**Tipos de Ação**:
- `CRIAR`: Criação de nova proposta
- `ATUALIZAR`: Alteração de dados da proposta (valor, cliente, etc.)
- `TRANSIÇÃO_ESTADO`: Mudança de estado da proposta
- `CANCELAR`: Cancelamento da proposta

---

## 3. Relacionamentos entre Entidades

### 3.1. Cliente ↔ Proposta
- **Tipo**: Um-para-Muitos (1:N)
- **Cardinalidade**: Um cliente pode ter múltiplas propostas
- **Obrigatoriedade**: Uma proposta DEVE ter um cliente (FK obrigatória)
- **Comportamento**: 
  - Se cliente for excluído, verificar se há propostas associadas
  - Proposta sempre referencia um cliente válido

### 3.2. Proposta ↔ Auditoria de Proposta
- **Tipo**: Um-para-Muitos (1:N)
- **Cardinalidade**: Uma proposta pode ter múltiplos registros de auditoria
- **Obrigatoriedade**: Um registro de auditoria DEVE referenciar uma proposta (FK obrigatória)
- **Comportamento**:
  - Toda ação sobre uma proposta gera um registro de auditoria
  - Auditoria é imutável (apenas leitura após criação)
  - Não há exclusão de registros de auditoria

### 3.3. Diagrama de Relacionamentos

```
┌──────────┐         ┌──────────┐         ┌──────────────┐
│ Cliente  │         │ Proposta │         │  Auditoria   │
│          │         │          │         │   Proposta   │
├──────────┤         ├──────────┤         ├──────────────┤
│ id (PK)  │◄──┐     │ id (PK)  │◄──┐     │ id (PK)      │
│ nome     │   │     │ cliente_ │   │     │ proposta_id  │
│ email    │   │     │   id(FK) │   │     │   (FK)       │
│ documento│   │     │ valor    │   │     │ acao         │
│          │   │     │ estado   │   │     │ estado_      │
└──────────┘   │     │ versao   │   │     │   anterior   │
              │     │          │   │     │ estado_novo  │
              │     └──────────┘   │     │ dados_       │
              │         │          │     │   anteriores │
              │         │          │     │ dados_novos  │
              │         │          │     │ usuario      │
              │         │          │     │ ocorrido_em  │
              │         └──────────┘     └──────────────┘
              │             1:N
              │
              1:N
```

---

## 4. Máquina de Estados (FSM) - Fluxo de Status da Proposta

### 4.1. Estados da Proposta

#### Estados Iniciais
- **RASCUNHO**: Estado inicial quando a proposta é criada. Permite edição completa.

#### Estados Intermediários
- **ENVIADA**: Proposta foi enviada ao cliente e aguarda resposta.

#### Estados Finais
- **ACEITA**: Proposta foi aceita pelo cliente. Estado final e imutável.
- **RECUSADA**: Proposta foi recusada pelo cliente. Estado final e imutável.
- **CANCELADA**: Proposta foi cancelada antes de ser respondida. Estado final e imutável.

### 4.2. Diagrama da Máquina de Estados

```
                    ┌──────────┐
                    │ RASCUNHO │ (Estado Inicial)
                    └────┬─────┘
                         │
         ┌───────────────┼───────────────┐
         │               │               │
         ▼               ▼               ▼
    ┌────────┐      ┌─────────┐      ┌──────────┐
    │ENVIADA │      │CANCELADA│      │CANCELADA  │
    └───┬────┘      └─────────┘      └──────────┘
        │           (Estado Final)    (Estado Final)
        │
    ┌───┴───────────────────────┐
    │                           │
    ▼                           ▼
┌────────┐                  ┌─────────┐
│ ACEITA │                  │RECUSADA │
└────────┘                  └─────────┘
(Estado Final)             (Estado Final)
```

### 4.3. Tabela de Transições Válidas

| Estado Atual | Estado Destino | Condição | Ação Automática |
|--------------|----------------|----------|-----------------|
| RASCUNHO | ENVIADA | Proposta válida | Define `enviado_em` |
| RASCUNHO | CANCELADA | Cancelamento manual | Define `atualizado_em` |
| ENVIADA | ACEITA | Cliente aceita | Define `respondido_em` |
| ENVIADA | RECUSADA | Cliente recusa | Define `respondido_em` |
| ENVIADA | CANCELADA | Cancelamento pós-envio | Define `atualizado_em` |

### 4.4. Transições Inválidas (Bloqueadas)

| Estado Atual | Estado Destino | Motivo do Bloqueio |
|--------------|----------------|-------------------|
| RASCUNHO | ACEITA | Não pode aceitar sem enviar |
| RASCUNHO | RECUSADA | Não pode recusar sem enviar |
| ENVIADA | RASCUNHO | Não pode voltar ao rascunho após envio |
| ACEITA | Qualquer outro | Estado final, imutável |
| RECUSADA | Qualquer outro | Estado final, imutável |
| CANCELADA | Qualquer outro | Estado final, imutável |

### 4.5. Regras de Negócio por Estado

#### RASCUNHO
- ✅ Permite edição de todos os campos (valor, cliente)
- ✅ Permite exclusão
- ✅ Permite transição para ENVIADA ou CANCELADA
- ❌ Não permite transição direta para ACEITA ou RECUSADA

#### ENVIADA
- ❌ Não permite edição de campos (exceto cancelamento)
- ✅ Permite transição para ACEITA, RECUSADA ou CANCELADA
- ❌ Não permite voltar para RASCUNHO
- ⚠️ Campo `enviado_em` é preenchido automaticamente

#### ACEITA (Estado Final)
- ❌ Não permite nenhuma alteração
- ❌ Não permite transição para outro estado
- ⚠️ Campo `respondido_em` é preenchido automaticamente
- 📋 Deve gerar registro de auditoria

#### RECUSADA (Estado Final)
- ❌ Não permite nenhuma alteração
- ❌ Não permite transição para outro estado
- ⚠️ Campo `respondido_em` é preenchido automaticamente
- 📋 Deve gerar registro de auditoria

#### CANCELADA (Estado Final)
- ❌ Não permite nenhuma alteração
- ❌ Não permite transição para outro estado
- 📋 Deve gerar registro de auditoria

### 4.6. Matriz de Transições (Resumo)

| De \ Para | RASCUNHO | ENVIADA | ACEITA | RECUSADA | CANCELADA |
|-----------|----------|---------|--------|----------|-----------|
| **RASCUNHO** | - | ✅ | ❌ | ❌ | ✅ |
| **ENVIADA** | ❌ | - | ✅ | ✅ | ✅ |
| **ACEITA** | ❌ | ❌ | - | ❌ | ❌ |
| **RECUSADA** | ❌ | ❌ | ❌ | - | ❌ |
| **CANCELADA** | ❌ | ❌ | ❌ | ❌ | - |

**Legenda**:
- ✅ = Transição válida
- ❌ = Transição inválida/bloqueada
- \- = Estado atual (sem transição)

### 4.7. Eventos e Ações Automáticas

| Evento | Estado Anterior | Estado Novo | Ações Automáticas |
|--------|----------------|-------------|-------------------|
| Criar Proposta | - | RASCUNHO | Define `criado_em`, `versao = 1` |
| Enviar Proposta | RASCUNHO | ENVIADA | Define `enviado_em`, incrementa `versao` |
| Aceitar Proposta | ENVIADA | ACEITA | Define `respondido_em`, incrementa `versao` |
| Recusar Proposta | ENVIADA | RECUSADA | Define `respondido_em`, incrementa `versao` |
| Cancelar Proposta | RASCUNHO/ENVIADA | CANCELADA | Incrementa `versao` |
| Atualizar Proposta | RASCUNHO | RASCUNHO | Incrementa `versao`, define `atualizado_em` |

---

## 5. Resumo Executivo

### 5.1. Entidades Principais
1. **Cliente**: Dados do cliente que recebe a proposta
2. **Proposta**: Entidade central com controle de estado via FSM
3. **Auditoria de Proposta**: Rastreamento completo de todas as alterações

### 5.2. Relacionamentos
- Cliente 1:N Proposta (obrigatório)
- Proposta 1:N Auditoria (obrigatório, imutável)

### 5.3. Estados da FSM
- **1 Estado Inicial**: RASCUNHO
- **1 Estado Intermediário**: ENVIADA
- **3 Estados Finais**: ACEITA, RECUSADA, CANCELADA

### 5.4. Transições
- **5 Transições Válidas** definidas
- **Todas as outras combinações são inválidas**
- Estados finais são imutáveis

### 5.5. Controles Implementados
- ✅ Optimistic Lock (via campo `versao`)
- ✅ Idempotência (via campo `idempotencia_key`)
- ✅ Auditoria completa (todos os eventos)
- ✅ Validação de transições de estado
- ✅ Regras de negócio por estado

---

## 6. Observações Importantes

1. **Imutabilidade de Estados Finais**: Uma vez que a proposta atinge um estado final (ACEITA, RECUSADA, CANCELADA), nenhuma alteração é permitida.

2. **Versionamento**: O campo `versao` é crítico para o controle de concorrência. Deve ser verificado em todas as operações de escrita.

3. **Auditoria Obrigatória**: Toda ação sobre uma proposta DEVE gerar um registro de auditoria, garantindo rastreabilidade completa.

4. **Idempotência**: A chave de idempotência garante que requisições duplicadas não criem propostas duplicadas.

5. **Integridade Referencial**: Cliente deve existir antes de criar proposta. Proposta não pode ser excluída se houver registros de auditoria (ou implementar soft delete).
