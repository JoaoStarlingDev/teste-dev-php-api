# Arquitetura em Camadas - Sistema de Gestão de Propostas

## 1. Visão Geral da Arquitetura

A arquitetura segue o padrão **Clean Architecture** (Arquitetura Limpa), separando responsabilidades em camadas bem definidas, garantindo:
- **Testabilidade**: Cada camada pode ser testada independentemente
- **Manutenibilidade**: Mudanças em uma camada não afetam outras
- **Reutilização**: Lógica de negócio isolada e reutilizável
- **Independência**: Domínio não depende de frameworks ou infraestrutura

---

## 2. Estrutura de Camadas

```
┌─────────────────────────────────────────────────────────┐
│              PRESENTATION LAYER                        │
│              (Controllers)                              │
└────────────────────┬────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────┐
│              APPLICATION LAYER                          │
│              (Services / Use Cases)                     │
└────────────────────┬────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────┐
│              DOMAIN LAYER                                │
│              (Entities, Enums, Rules, Exceptions)        │
└────────────────────┬────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────┐
│              INFRASTRUCTURE LAYER                       │
│              (Repositories, Models, Database)           │
└─────────────────────────────────────────────────────────┘
```

---

## 3. Detalhamento das Camadas

### 3.1. DOMAIN LAYER (Camada de Domínio)

**Localização**: `src/Domain/`

**Responsabilidade Principal**: 
Contém a lógica de negócio pura, regras de domínio e entidades. É a camada mais interna e **não depende de nenhuma outra camada**.

#### 3.1.1. Estrutura

```
Domain/
├── Proposta/
│   ├── Proposta.php                    # Entidade de domínio
│   ├── EstadoProposta.php              # Enum de estados
│   ├── PropostaRepositoryInterface.php # Interface do repositório
│   ├── Exceptions/
│   │   ├── PropostaNaoEncontradaException.php
│   │   ├── TransicaoEstadoInvalidaException.php
│   │   ├── PropostaNaoPodeSerEditadaException.php
│   │   └── VersaoConcorrenciaException.php
│   └── ValueObjects/
│       ├── Valor.php
│       ├── Cliente.php
│       └── IdempotenciaKey.php
├── Auditoria/
│   ├── Auditoria.php                   # Entidade de domínio
│   ├── AuditoriaRepositoryInterface.php
│   └── Exceptions/
│       └── AuditoriaException.php
└── Shared/
    └── Exceptions/
        └── DomainException.php         # Exceção base do domínio
```

#### 3.1.2. Componentes

**Entidades (Entities)**:
- Representam objetos de negócio com identidade única
- Contêm regras de negócio e comportamentos
- Exemplos: `Proposta`, `Auditoria`

**Enums**:
- Representam valores fixos do domínio
- Contêm lógica de validação de transições
- Exemplo: `EstadoProposta` (com métodos `podeTransicionarPara()`, `permiteEdicao()`)

**Value Objects**:
- Objetos imutáveis que representam conceitos do domínio
- Garantem invariantes e validações
- Exemplos: `Valor`, `Cliente`, `IdempotenciaKey`

**Interfaces de Repositório**:
- Definem contratos para persistência
- Permitem inversão de dependência
- Exemplo: `PropostaRepositoryInterface`

**Exceções de Domínio**:
- Representam erros de regras de negócio
- Específicas do domínio
- Exemplos: `TransicaoEstadoInvalidaException`, `VersaoConcorrenciaException`

#### 3.1.3. Regras de Negócio no Domínio

**Onde ficam as regras de negócio**:
- ✅ **Na entidade `Proposta`**:
  - Validação de transições de estado
  - Regras de edição (apenas RASCUNHO permite edição)
  - Incremento de versão
  - Validação de versão para optimistic lock

- ✅ **No enum `EstadoProposta`**:
  - Quais transições são válidas
  - Quais estados permitem edição
  - Quais são estados finais

- ✅ **Nos Value Objects**:
  - Validação de formato e valores
  - Invariantes (ex: valor > 0)

**Princípios**:
- Regras de negócio **nunca** ficam em Services ou Controllers
- Entidades são "ricas" (Rich Domain Model), não apenas DTOs
- Toda validação de negócio está no domínio

---

### 3.2. APPLICATION LAYER (Camada de Aplicação)

**Localização**: `src/Application/`

**Responsabilidade Principal**: 
Orquestra o domínio para executar casos de uso específicos. Coordena entidades, repositórios e serviços de infraestrutura.

#### 3.2.1. Estrutura

```
Application/
├── Services/
│   ├── PropostaService.php
│   ├── AuditoriaService.php
│   └── ClienteService.php
├── DTOs/
│   ├── CriarPropostaDTO.php
│   ├── AtualizarPropostaDTO.php
│   ├── TransicionarEstadoDTO.php
│   └── PropostaResponseDTO.php
├── Mappers/
│   └── PropostaMapper.php              # Converte entre camadas
└── Exceptions/
    └── ApplicationException.php
```

#### 3.2.2. Componentes

**Services (Serviços de Aplicação)**:
- Implementam casos de uso específicos
- Orquestram múltiplas entidades e repositórios
- Coordenam transações
- Implementam idempotência
- Registram auditoria

**DTOs (Data Transfer Objects)**:
- Objetos de transferência de dados entre camadas
- Não contêm lógica de negócio
- Validam dados de entrada
- Exemplos: `CriarPropostaDTO`, `PropostaResponseDTO`

**Mappers**:
- Convertem entre entidades de domínio e DTOs
- Isolam transformações de dados
- Exemplo: `PropostaMapper::toDTO()`, `PropostaMapper::toEntity()`

#### 3.2.3. Responsabilidades dos Services

**PropostaService**:
- ✅ Criar proposta (com idempotência)
- ✅ Buscar proposta por ID
- ✅ Listar propostas (com paginação)
- ✅ Atualizar proposta (com optimistic lock)
- ✅ Transicionar estado (valida versão)
- ✅ Coordenar registro de auditoria
- ❌ **NÃO contém regras de negócio** (delega para entidades)

**AuditoriaService**:
- ✅ Registrar eventos de auditoria
- ✅ Buscar histórico de auditoria
- ✅ Filtrar por entidade/tipo

**ClienteService**:
- ✅ Criar cliente
- ✅ Buscar cliente
- ✅ Validar existência de cliente

#### 3.2.4. Fluxo Típico de um Service

```
1. Validar DTO de entrada
2. Buscar entidades necessárias (via Repository)
3. Executar operação na entidade (regra de negócio)
4. Persistir alterações (via Repository)
5. Registrar auditoria (via AuditoriaService)
6. Retornar DTO de resposta
```

---

### 3.3. INFRASTRUCTURE LAYER (Camada de Infraestrutura)

**Localização**: `src/Infrastructure/`

**Responsabilidade Principal**: 
Implementa detalhes técnicos: persistência, acesso a dados, integrações externas.

#### 3.3.1. Estrutura

```
Infrastructure/
├── Repository/
│   ├── PropostaRepository.php          # Implementa PropostaRepositoryInterface
│   ├── AuditoriaRepository.php         # Implementa AuditoriaRepositoryInterface
│   └── ClienteRepository.php
├── Models/
│   ├── PropostaModel.php               # Model do ORM/Active Record
│   ├── AuditoriaModel.php
│   └── ClienteModel.php
├── Database/
│   ├── Connection.php
│   └── Migrations/
└── Exceptions/
    └── DatabaseException.php
```

#### 3.3.2. Componentes

**Repositories (Implementações)**:
- Implementam interfaces do domínio
- Convertem entre Models (banco) e Entities (domínio)
- Executam queries SQL/ORM
- Tratam soft delete
- Implementam cache (se necessário)

**Models**:
- Representam tabelas do banco de dados
- Podem ser Active Record ou Data Mapper
- Contêm apenas lógica de persistência
- Exemplo: `PropostaModel` mapeia tabela `propostas`

**Database**:
- Configuração de conexão
- Migrations
- Seeders

#### 3.3.3. Responsabilidades dos Repositories

**PropostaRepository**:
- ✅ Salvar proposta (INSERT/UPDATE)
- ✅ Buscar por ID (com soft delete)
- ✅ Buscar por idempotência key
- ✅ Listar propostas (com paginação e filtros)
- ✅ Converter Model → Entity
- ✅ Converter Entity → Model
- ❌ **NÃO contém regras de negócio**

**AuditoriaRepository**:
- ✅ Registrar auditoria (INSERT)
- ✅ Buscar por proposta/entidade
- ✅ Filtrar por data/ação

**ClienteRepository**:
- ✅ CRUD de clientes
- ✅ Buscar por email/documento
- ✅ Validar existência

---

### 3.4. PRESENTATION LAYER (Camada de Apresentação)

**Localização**: `src/Presentation/` ou `src/Http/`

**Responsabilidade Principal**: 
Gerencia requisições HTTP, valida entrada, formata saída. É a camada mais externa.

#### 3.4.1. Estrutura

```
Presentation/
├── Controllers/
│   ├── PropostaController.php
│   ├── AuditoriaController.php
│   └── ClienteController.php
├── Requests/
│   ├── CriarPropostaRequest.php        # Validação de entrada
│   ├── AtualizarPropostaRequest.php
│   └── TransicionarEstadoRequest.php
├── Resources/
│   ├── PropostaResource.php            # Formatação de saída
│   └── AuditoriaResource.php
├── Middleware/
│   ├── AuthenticateMiddleware.php
│   └── ValidationMiddleware.php
└── Router.php
```

#### 3.4.2. Componentes

**Controllers**:
- Recebem requisições HTTP
- Validam entrada (via Requests)
- Chamam Services
- Formatam resposta (via Resources)
- Tratam exceções e retornam HTTP status codes

**Requests (Form Requests)**:
- Validam dados de entrada HTTP
- Regras de validação (required, email, min, max, etc.)
- Sanitização de dados
- Exemplo: `CriarPropostaRequest` valida JSON de entrada

**Resources (API Resources)**:
- Formatam dados de saída
- Transformam DTOs em JSON
- Incluem/excluem campos conforme necessário
- Exemplo: `PropostaResource` formata resposta da API

**Middleware**:
- Autenticação
- Autorização
- Validação global
- Logging

#### 3.4.3. Responsabilidades dos Controllers

**PropostaController**:
- ✅ Receber requisições HTTP (GET, POST, PATCH)
- ✅ Validar entrada (via Request)
- ✅ Chamar Service apropriado
- ✅ Tratar exceções e retornar HTTP status
- ✅ Formatar resposta JSON (via Resource)
- ❌ **NÃO contém regras de negócio**
- ❌ **NÃO acessa banco diretamente**

**Fluxo Típico de um Controller**:
```
1. Receber requisição HTTP
2. Validar entrada (Request)
3. Chamar Service
4. Capturar exceções
5. Formatar resposta (Resource)
6. Retornar JSON com status HTTP
```

---

## 4. Fluxo de Dados Completo

### 4.1. Exemplo: Criar Proposta

```
HTTP POST /api/v1/propostas
    │
    ▼
[Controller] PropostaController::criar()
    │ - Valida entrada (Request)
    │ - Extrai dados do JSON
    │
    ▼
[Service] PropostaService::criar()
    │ - Valida DTO
    │ - Verifica idempotência (Repository)
    │ - Busca Cliente (Repository)
    │
    ▼
[Domain] new Proposta(Cliente, Valor, IdempotenciaKey)
    │ - Valida regras de negócio
    │ - Define estado inicial (RASCUNHO)
    │ - Define versão inicial (1)
    │
    ▼
[Service] PropostaService::criar() (continuação)
    │ - Salva proposta (Repository)
    │ - Registra auditoria (AuditoriaService)
    │
    ▼
[Repository] PropostaRepository::salvar()
    │ - Converte Entity → Model
    │ - Executa INSERT no banco
    │
    ▼
[Service] PropostaService::criar() (finalização)
    │ - Converte Entity → DTO
    │
    ▼
[Controller] PropostaController::criar() (finalização)
    │ - Formata resposta (Resource)
    │ - Retorna JSON 201 Created
```

### 4.2. Exemplo: Transicionar Estado

```
HTTP POST /api/v1/propostas/1/transicionar-estado
    │
    ▼
[Controller] PropostaController::transicionarEstado()
    │ - Valida entrada (Request)
    │
    ▼
[Service] PropostaService::transicionarEstado()
    │ - Busca proposta (Repository)
    │ - Valida versão (optimistic lock)
    │
    ▼
[Domain] Proposta::transicionarEstado(EstadoProposta)
    │ - Valida regra de negócio: pode transicionar?
    │ - Valida regra de negócio: estado permite?
    │ - Executa transição
    │ - Incrementa versão
    │
    ▼
[Service] PropostaService::transicionarEstado() (continuação)
    │ - Salva proposta (Repository)
    │ - Registra auditoria (AuditoriaService)
    │
    ▼
[Repository] PropostaRepository::salvar()
    │ - Executa UPDATE no banco
    │
    ▼
[Controller] Retorna JSON 200 OK
```

---

## 5. Onde Ficam as Regras de Negócio?

### 5.1. Regras de Negócio no DOMAIN

✅ **Entidade Proposta**:
- Validação de transições de estado
- Regras de edição (apenas RASCUNHO)
- Controle de versão (optimistic lock)
- Validação de invariantes

✅ **Enum EstadoProposta**:
- Quais transições são válidas
- Quais estados permitem edição
- Quais são estados finais

✅ **Value Objects**:
- Validação de formato (email, documento)
- Validação de valores (valor > 0)
- Invariantes

✅ **Exceções de Domínio**:
- Representam violações de regras de negócio

### 5.2. O que NÃO é Regra de Negócio

❌ **Validação de Formato HTTP** (Request):
- Validação de JSON válido
- Campos obrigatórios na requisição
- Tipos de dados (string, number)

❌ **Lógica de Persistência** (Repository):
- Queries SQL
- Mapeamento Model ↔ Entity
- Soft delete

❌ **Orquestração** (Service):
- Coordenação de múltiplas operações
- Transações
- Idempotência (lógica técnica)

---

## 6. Princípios e Padrões

### 6.1. Dependency Inversion Principle (DIP)

- **Domain** não depende de outras camadas
- **Application** depende de **Domain** (interfaces)
- **Infrastructure** implementa interfaces do **Domain**
- **Presentation** depende de **Application**

### 6.2. Single Responsibility Principle (SRP)

- Cada camada tem uma responsabilidade única
- Cada classe tem uma responsabilidade específica

### 6.3. Separation of Concerns

- Regras de negócio isoladas no Domain
- Lógica de aplicação isolada em Services
- Detalhes técnicos isolados em Infrastructure

### 6.4. Repository Pattern

- Abstrai acesso a dados
- Permite trocar implementação (banco, cache, mock)
- Facilita testes

### 6.5. DTO Pattern

- Isola camadas
- Evita expor entidades de domínio
- Facilita versionamento de API

---

## 7. Mapeamento de Responsabilidades

| Responsabilidade | Camada | Componente |
|------------------|--------|------------|
| **Regras de negócio** | Domain | Entities, Enums, Value Objects |
| **Validação de formato HTTP** | Presentation | Requests |
| **Validação de dados de entrada** | Application | DTOs |
| **Orquestração de casos de uso** | Application | Services |
| **Persistência de dados** | Infrastructure | Repositories, Models |
| **Formatação de resposta** | Presentation | Resources |
| **Tratamento de exceções HTTP** | Presentation | Controllers |
| **Idempotência** | Application | Services |
| **Auditoria** | Application | Services (coordena) |
| **Optimistic Lock** | Domain + Application | Entity (valida) + Service (coordena) |

---

## 8. Resumo da Arquitetura

### 8.1. Camadas e Responsabilidades

1. **Domain**: Regras de negócio puras, entidades, enums, value objects
2. **Application**: Casos de uso, orquestração, DTOs, services
3. **Infrastructure**: Persistência, repositórios, models, banco de dados
4. **Presentation**: HTTP, controllers, requests, resources

### 8.2. Fluxo de Dependências

```
Presentation → Application → Domain ← Infrastructure
```

- Domain é independente
- Infrastructure implementa interfaces do Domain
- Application orquestra Domain e Infrastructure
- Presentation coordena HTTP e chama Application

### 8.3. Regras de Negócio

✅ **Ficam no Domain**:
- Validação de transições de estado
- Regras de edição
- Validação de invariantes
- Lógica de versionamento

❌ **NÃO ficam em**:
- Controllers (apenas HTTP)
- Services (apenas orquestração)
- Repositories (apenas persistência)

---

## 9. Benefícios desta Arquitetura

1. **Testabilidade**: Cada camada testável independentemente
2. **Manutenibilidade**: Mudanças isoladas por camada
3. **Reutilização**: Lógica de negócio reutilizável
4. **Clareza**: Responsabilidades bem definidas
5. **Escalabilidade**: Fácil adicionar novas funcionalidades
6. **Independência**: Domain não depende de frameworks

---

## 10. Estrutura de Diretórios Final

```
src/
├── Domain/                    # Regras de negócio
│   ├── Proposta/
│   ├── Auditoria/
│   └── Shared/
├── Application/               # Casos de uso
│   ├── Services/
│   ├── DTOs/
│   └── Mappers/
├── Infrastructure/            # Detalhes técnicos
│   ├── Repository/
│   ├── Models/
│   └── Database/
└── Presentation/              # HTTP/API
    ├── Controllers/
    ├── Requests/
    ├── Resources/
    └── Middleware/
```
