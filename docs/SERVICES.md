# Services - Camada de Aplicação

## Visão Geral

Services responsáveis por orquestrar operações de negócio, incluindo:
- Validação de regras de negócio
- Controle de versão (optimistic lock)
- Registro de auditoria
- Coordenação entre repositórios e entidades

---

## 1. ClienteService

**Arquivo**: `src/Application/Services/ClienteService.php`

### 1.1. Responsabilidades

- Criar novos clientes
- Validar unicidade de email e documento
- Buscar clientes por ID

### 1.2. Métodos

#### `criar(string $nome, string $email, ?string $documento = null): Cliente`

Cria um novo cliente com validações:
- Verifica se email já existe
- Verifica se documento já existe (se fornecido)
- Valida dados através da entidade Cliente
- Persiste no repositório

**Exemplo**:
```php
$clienteService = new ClienteService($clienteRepository);

try {
    $cliente = $clienteService->criar(
        'João Silva',
        'joao@example.com',
        '123.456.789-00'
    );
} catch (\DomainException $e) {
    // Cliente já existe
}
```

#### `buscarPorId(int $id): ?Cliente`

Busca cliente por ID.

#### `existe(int $id): bool`

Verifica se cliente existe.

---

## 2. PropostaService

**Arquivo**: `src/Application/Services/PropostaService.php`

### 2.1. Responsabilidades

- Criar propostas em estado RASCUNHO (DRAFT)
- Submeter propostas (RASCUNHO → ENVIADA)
- Aprovar propostas (ENVIADA → ACEITA)
- Rejeitar propostas (ENVIADA → RECUSADA)
- Cancelar propostas (RASCUNHO/ENVIADA → CANCELADA)
- Controle de versão (optimistic lock)
- Registro de auditoria automático
- Validação de regras de negócio

### 2.2. Métodos

#### `criarProposta(int $clienteId, float $valor, ?string $idempotenciaKey = null, ?string $usuario = null): Proposta`

Cria uma nova proposta em estado **RASCUNHO (DRAFT)**.

**Características**:
- ✅ Verifica idempotência (se chave fornecida)
- ✅ Valida existência do cliente
- ✅ Cria proposta sempre em RASCUNHO
- ✅ Registra auditoria de criação

**Parâmetros**:
- `$clienteId`: ID do cliente
- `$valor`: Valor da proposta (deve ser > 0)
- `$idempotenciaKey`: Chave de idempotência (opcional)
- `$usuario`: Usuário que está criando (opcional)

**Exemplo**:
```php
$propostaService = new PropostaService(
    $propostaRepository,
    $clienteRepository,
    $auditoriaRepository
);

$proposta = $propostaService->criarProposta(
    clienteId: 1,
    valor: 1500.00,
    idempotenciaKey: 'proposta-001',
    usuario: 'admin'
);
```

---

#### `submeterProposta(int $propostaId, int $versaoEsperada, ?string $usuario = null): Proposta`

Submete uma proposta (transição **RASCUNHO → ENVIADA**).

**Características**:
- ✅ Valida versão (optimistic lock)
- ✅ Valida transição de estado
- ✅ Executa transição
- ✅ Registra auditoria

**Parâmetros**:
- `$propostaId`: ID da proposta
- `$versaoEsperada`: Versão esperada (optimistic lock)
- `$usuario`: Usuário que está submetendo (opcional)

**Exemplo**:
```php
$proposta = $propostaService->submeterProposta(
    propostaId: 1,
    versaoEsperada: 1,
    usuario: 'admin'
);
```

**Erros possíveis**:
- `DomainException`: Proposta não encontrada
- `DomainException`: Versão incorreta (concorrência)
- `TransicaoEstadoInvalidaException`: Transição inválida
- `EstadoFinalImutavelException`: Estado final (não aplicável aqui)

---

#### `aprovarProposta(int $propostaId, int $versaoEsperada, ?string $usuario = null): Proposta`

Aprova uma proposta (transição **ENVIADA → ACEITA**).

**Características**:
- ✅ Valida versão (optimistic lock)
- ✅ Valida que proposta está em ENVIADA
- ✅ Valida transição de estado
- ✅ Executa transição para ACEITA (estado final)
- ✅ Registra auditoria

**Parâmetros**:
- `$propostaId`: ID da proposta
- `$versaoEsperada`: Versão esperada (optimistic lock)
- `$usuario`: Usuário que está aprovando (opcional)

**Exemplo**:
```php
$proposta = $propostaService->aprovarProposta(
    propostaId: 1,
    versaoEsperada: 2,
    usuario: 'cliente'
);
```

**Erros possíveis**:
- `DomainException`: Proposta não encontrada ou versão incorreta
- `TransicaoEstadoInvalidaException`: Proposta não está em ENVIADA

---

#### `rejeitarProposta(int $propostaId, int $versaoEsperada, ?string $usuario = null): Proposta`

Rejeita uma proposta (transição **ENVIADA → RECUSADA**).

**Características**:
- ✅ Valida versão (optimistic lock)
- ✅ Valida que proposta está em ENVIADA
- ✅ Valida transição de estado
- ✅ Executa transição para RECUSADA (estado final)
- ✅ Registra auditoria

**Parâmetros**:
- `$propostaId`: ID da proposta
- `$versaoEsperada`: Versão esperada (optimistic lock)
- `$usuario`: Usuário que está rejeitando (opcional)

**Exemplo**:
```php
$proposta = $propostaService->rejeitarProposta(
    propostaId: 1,
    versaoEsperada: 2,
    usuario: 'cliente'
);
```

---

#### `cancelarProposta(int $propostaId, int $versaoEsperada, ?string $usuario = null): Proposta`

Cancela uma proposta (transição **RASCUNHO/ENVIADA → CANCELADA**).

**Características**:
- ✅ Valida versão (optimistic lock)
- ✅ Valida permissão de cancelamento
- ✅ Valida transição de estado
- ✅ Executa transição para CANCELADA (estado final)
- ✅ Registra auditoria

**Parâmetros**:
- `$propostaId`: ID da proposta
- `$versaoEsperada`: Versão esperada (optimistic lock)
- `$usuario`: Usuário que está cancelando (opcional)

**Exemplo**:
```php
$proposta = $propostaService->cancelarProposta(
    propostaId: 1,
    versaoEsperada: 1,
    usuario: 'admin'
);
```

**Erros possíveis**:
- `DomainException`: Proposta não encontrada ou versão incorreta
- `EstadoFinalImutavelException`: Proposta já está em estado final
- `TransicaoEstadoInvalidaException`: Transição não permitida

---

#### `buscarPorId(int $id): ?Proposta`

Busca proposta por ID.

---

## 3. Controle de Versão (Optimistic Lock)

### 3.1. Como Funciona

Todas as operações que modificam a proposta exigem o parâmetro `$versaoEsperada`:

1. Cliente envia requisição com versão atual da proposta
2. Service verifica se versão ainda corresponde
3. Se corresponder: executa operação e incrementa versão
4. Se não corresponder: lança exceção (concorrência detectada)

### 3.2. Fluxo

```
1. Cliente busca proposta (versão = 1)
2. Cliente tenta submeter (envia versão = 1)
3. Service verifica: versão atual == 1? ✅
4. Service executa transição
5. Service incrementa versão (agora = 2)
6. Service salva proposta
```

### 3.3. Detecção de Concorrência

```
Cliente A: Busca proposta (versão = 1)
Cliente B: Busca proposta (versão = 1)
Cliente A: Submete proposta (versão esperada = 1) ✅ (versão atualiza para 2)
Cliente B: Tenta submeter (versão esperada = 1) ❌ (versão atual é 2)
→ Exceção: "Proposta foi modificada por outro processo"
```

### 3.4. Implementação

```php
private function buscarEValidarVersao(int $propostaId, int $versaoEsperada): Proposta
{
    $proposta = $this->propostaRepository->buscarPorId($propostaId);

    if ($proposta === null) {
        throw new \DomainException("Proposta não encontrada");
    }

    // Optimistic lock
    if (!$proposta->verificarVersao($versaoEsperada)) {
        throw new \DomainException(
            "Proposta foi modificada. Versão atual: {$proposta->getVersao()}, esperada: {$versaoEsperada}"
        );
    }

    return $proposta;
}
```

---

## 4. Registro de Auditoria

### 4.1. Automático

Todas as operações registram auditoria automaticamente:
- ✅ Criação de proposta
- ✅ Submissão de proposta
- ✅ Aprovação de proposta
- ✅ Rejeição de proposta
- ✅ Cancelamento de proposta

### 4.2. Dados Registrados

Cada registro de auditoria contém:
- **Entidade**: 'Proposta'
- **ID da Entidade**: ID da proposta
- **Ação**: CRIAR, TRANSIÇÃO_ESTADO, CANCELAR
- **Estado Anterior**: Estado antes da operação
- **Estado Novo**: Estado após a operação
- **Dados Anteriores**: Snapshot completo antes
- **Dados Novos**: Snapshot completo depois
- **Usuário**: Usuário que executou a ação
- **Timestamp**: Data/hora da operação

### 4.3. Implementação

```php
private function registrarAuditoria(
    Proposta $proposta,
    string $acao,
    ?EstadoProposta $estadoAnterior,
    ?EstadoProposta $estadoNovo,
    array $dadosAnteriores,
    array $dadosNovos,
    ?string $usuario
): void {
    // Adiciona estados aos dados
    if ($estadoAnterior !== null) {
        $dadosAnteriores['estado'] = $estadoAnterior->value;
    }
    if ($estadoNovo !== null) {
        $dadosNovos['estado'] = $estadoNovo->value;
    }

    $auditoria = new Auditoria(
        'Proposta',
        $proposta->getId(),
        $acao,
        $dadosAnteriores,
        $dadosNovos,
        $usuario
    );

    $this->auditoriaRepository->registrar($auditoria);
}
```

---

## 5. Validação de Regras de Negócio

### 5.1. Validador de Transições

O service utiliza `ValidadorTransicaoEstado` para validar todas as transições:

```php
// Valida transição
$this->validador->validarTransicao($estadoAtual, $novoEstado);

// Valida permissão de cancelamento
$this->validador->validarPermissaoCancelamento($estadoAtual);
```

### 5.2. Regras Aplicadas

- ✅ Estados finais são imutáveis
- ✅ Apenas transições válidas da FSM são permitidas
- ✅ Apenas RASCUNHO permite edição
- ✅ Versão deve corresponder (optimistic lock)

### 5.3. Exceções Lançadas

- `EstadoFinalImutavelException`: Tentativa de alterar estado final
- `TransicaoEstadoInvalidaException`: Transição não permitida
- `PropostaNaoPodeSerEditadaException`: Tentativa de editar proposta não em RASCUNHO
- `DomainException`: Proposta não encontrada ou versão incorreta

---

## 6. Fluxo Completo de Uso

### 6.1. Criar e Submeter Proposta

```php
// 1. Criar cliente
$cliente = $clienteService->criar(
    'João Silva',
    'joao@example.com',
    '123.456.789-00'
);

// 2. Criar proposta em RASCUNHO
$proposta = $propostaService->criarProposta(
    clienteId: $cliente->getId(),
    valor: 1500.00,
    idempotenciaKey: 'proposta-001',
    usuario: 'admin'
);
// Estado: RASCUNHO, Versão: 1

// 3. Submeter proposta
$proposta = $propostaService->submeterProposta(
    propostaId: $proposta->getId(),
    versaoEsperada: 1,
    usuario: 'admin'
);
// Estado: ENVIADA, Versão: 2
```

### 6.2. Aprovar Proposta

```php
// Cliente aprova proposta
$proposta = $propostaService->aprovarProposta(
    propostaId: 1,
    versaoEsperada: 2,
    usuario: 'cliente'
);
// Estado: ACEITA, Versão: 3 (estado final - imutável)
```

### 6.3. Cancelar Proposta

```php
// Cancelar proposta em RASCUNHO
$proposta = $propostaService->cancelarProposta(
    propostaId: 1,
    versaoEsperada: 1,
    usuario: 'admin'
);
// Estado: CANCELADA, Versão: 2 (estado final - imutável)
```

---

## 7. Estrutura de Arquivos

```
src/Application/Services/
├── ClienteService.php          ✅ Criar cliente
└── PropostaService.php         ✅ Todas as operações de proposta

src/Domain/Cliente/
├── Cliente.php                 ✅ Entidade de domínio
└── ClienteRepositoryInterface.php ✅ Interface do repositório
```

---

## 8. Resumo

✅ **ClienteService**: Criar cliente com validações
✅ **PropostaService**: Todas as operações de proposta
✅ **Optimistic Lock**: Controle de versão em todas as operações
✅ **Auditoria**: Registro automático de todas as ações
✅ **Validação**: Regras de negócio validadas via ValidadorTransicaoEstado
✅ **Exceções**: Exceções específicas para cada tipo de erro

Os services estão prontos para uso e seguem todas as boas práticas definidas na arquitetura.
