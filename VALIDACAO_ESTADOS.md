# Sistema de Validação de Estados - Proposta

## Visão Geral

Sistema robusto de validação de estados da proposta, garantindo **clareza** e **segurança** através de:
- Enum tipado com métodos auxiliares
- Validador centralizado de transições
- Exceções específicas de domínio
- Regras claras para estados finais imutáveis

---

## 1. Enum EstadoProposta

**Arquivo**: `src/Domain/Proposta/EstadoProposta.php`

### 1.1. Estados Disponíveis

```php
EstadoProposta::RASCUNHO   // Estado inicial
EstadoProposta::ENVIADA    // Estado intermediário
EstadoProposta::ACEITA     // Estado final (imutável)
EstadoProposta::RECUSADA   // Estado final (imutável)
EstadoProposta::CANCELADA  // Estado final (imutável)
```

### 1.2. Métodos Principais

#### Verificação de Tipo de Estado
- `isFinal()` - Verifica se é estado final (imutável)
- `isInicial()` - Verifica se é estado inicial
- `isIntermediario()` - Verifica se é estado intermediário

#### Permissões
- `permiteEdicao()` - Retorna `true` apenas para RASCUNHO
- `podeTransicionarPara(EstadoProposta $novoEstado)` - Valida transição

#### Informações
- `estadosValidosParaTransicao()` - Lista estados válidos
- `descricao()` - Descrição legível do estado
- `campoTimestampRequerido()` - Retorna campo de timestamp necessário

#### Métodos Estáticos
- `todos()` - Retorna todos os estados
- `estadosFinais()` - Retorna apenas estados finais
- `estadosNaoFinais()` - Retorna estados não finais

### 1.3. Exemplo de Uso

```php
$estado = EstadoProposta::RASCUNHO;

// Verificar tipo
if ($estado->isFinal()) {
    // Estado final - imutável
}

// Verificar permissões
if ($estado->permiteEdicao()) {
    // Pode editar
}

// Verificar transição
if ($estado->podeTransicionarPara(EstadoProposta::ENVIADA)) {
    // Transição válida
}

// Obter estados válidos
$estadosValidos = $estado->estadosValidosParaTransicao();
// Retorna: [EstadoProposta::ENVIADA, EstadoProposta::CANCELADA]
```

---

## 2. ValidadorTransicaoEstado

**Arquivo**: `src/Domain/Proposta/ValidadorTransicaoEstado.php`

### 2.1. Responsabilidades

Classe centralizada responsável por validar todas as operações relacionadas a estados:
- Validação de transições
- Validação de permissão de edição
- Validação de permissão de cancelamento
- Validação de permissão de alteração

### 2.2. Métodos

#### `validarTransicao(EstadoProposta $estadoAtual, EstadoProposta $novoEstado): void`
Valida se uma transição é permitida. Lança exceções se:
- Estado atual é final (imutável)
- Transição não é válida segundo a FSM
- Tentativa de transicionar para o mesmo estado

#### `validarPermissaoEdicao(EstadoProposta $estadoAtual): void`
Valida se o estado permite edição. Lança exceção se:
- Estado não é RASCUNHO

#### `validarPermissaoCancelamento(EstadoProposta $estadoAtual): void`
Valida se o estado permite cancelamento. Lança exceção se:
- Estado é final

#### `validarPermissaoAlteracao(EstadoProposta $estadoAtual): void`
Valida se o estado permite qualquer alteração. Lança exceção se:
- Estado é final

#### `obterTransicoesValidas(EstadoProposta $estadoAtual): string`
Retorna mensagem descritiva sobre transições válidas.

### 2.3. Exemplo de Uso

```php
$validador = new ValidadorTransicaoEstado();

try {
    // Validar transição
    $validador->validarTransicao(
        EstadoProposta::RASCUNHO,
        EstadoProposta::ENVIADA
    );
    
    // Transição válida - prosseguir
} catch (TransicaoEstadoInvalidaException $e) {
    // Tratar erro de transição
} catch (EstadoFinalImutavelException $e) {
    // Tratar erro de estado final
}

// Validar permissão de edição
try {
    $validador->validarPermissaoEdicao(EstadoProposta::RASCUNHO);
    // Pode editar
} catch (PropostaNaoPodeSerEditadaException $e) {
    // Não pode editar
}
```

---

## 3. Exceções de Domínio

### 3.1. EstadoFinalImutavelException

**Arquivo**: `src/Domain/Proposta/Exceptions/EstadoFinalImutavelException.php`

**Quando é lançada**:
- Tentativa de transicionar de um estado final
- Tentativa de editar proposta em estado final
- Tentativa de cancelar proposta em estado final
- Qualquer alteração em estado final

**Estados finais**: ACEITA, RECUSADA, CANCELADA

**Métodos**:
- `getEstado()` - Retorna o EstadoProposta
- `getEstadoValue()` - Retorna o valor como string

**Exemplo**:
```php
try {
    $proposta->transicionarEstado(EstadoProposta::ACEITA);
} catch (EstadoFinalImutavelException $e) {
    echo "Estado final: " . $e->getEstadoValue();
    // Output: "Estado final: aceita"
}
```

### 3.2. TransicaoEstadoInvalidaException

**Arquivo**: `src/Domain/Proposta/Exceptions/TransicaoEstadoInvalidaException.php`

**Quando é lançada**:
- Tentativa de transição não permitida pela FSM
- Tentativa de transicionar para o mesmo estado

**Métodos**:
- `getEstadoAtual()` - Estado atual
- `getEstadoDesejado()` - Estado desejado
- `getEstadoAtualValue()` - Valor do estado atual
- `getEstadoDesejadoValue()` - Valor do estado desejado

**Exemplo**:
```php
try {
    $proposta->transicionarEstado(EstadoProposta::ACEITA);
} catch (TransicaoEstadoInvalidaException $e) {
    echo "De: " . $e->getEstadoAtualValue();
    echo "Para: " . $e->getEstadoDesejadoValue();
}
```

### 3.3. PropostaNaoPodeSerEditadaException

**Arquivo**: `src/Domain/Proposta/Exceptions/PropostaNaoPodeSerEditadaException.php`

**Quando é lançada**:
- Tentativa de editar proposta que não está em RASCUNHO

**Métodos**:
- `getEstado()` - Estado atual
- `getEstadoValue()` - Valor do estado

**Exemplo**:
```php
try {
    $proposta->atualizarValor(new Valor(2000.00));
} catch (PropostaNaoPodeSerEditadaException $e) {
    echo "Estado atual: " . $e->getEstadoValue();
    // Output: "Estado atual: enviada"
}
```

---

## 4. Regras de Estados Finais

### 4.1. Estados Finais

Os seguintes estados são **finais** e **imutáveis**:
- `ACEITA`
- `RECUSADA`
- `CANCELADA`

### 4.2. Restrições de Estados Finais

Estados finais **NÃO permitem**:
- ❌ Transições para outros estados
- ❌ Edição de dados (valor, cliente, etc.)
- ❌ Cancelamento
- ❌ Qualquer alteração

### 4.3. Verificação

```php
$estado = EstadoProposta::ACEITA;

// Verificar se é final
if ($estado->isFinal()) {
    // Estado final - imutável
    throw new EstadoFinalImutavelException($estado);
}

// Verificar se permite edição
if (!$estado->permiteEdicao()) {
    // Não permite edição
}

// Verificar transições válidas
$transicoes = $estado->estadosValidosParaTransicao();
// Retorna: [] (array vazio - sem transições)
```

---

## 5. Máquina de Estados (FSM)

### 5.1. Transições Válidas

```
RASCUNHO → ENVIADA
RASCUNHO → CANCELADA
ENVIADA → ACEITA
ENVIADA → RECUSADA
ENVIADA → CANCELADA
```

### 5.2. Transições Inválidas

Todas as outras combinações são inválidas, incluindo:
- Qualquer transição a partir de estados finais
- RASCUNHO → ACEITA (sem passar por ENVIADA)
- RASCUNHO → RECUSADA (sem passar por ENVIADA)
- ENVIADA → RASCUNHO (não permite retrocesso)

### 5.3. Diagrama

```
[RASCUNHO] ──→ [ENVIADA] ──→ [ACEITA] (final)
     │              │
     │              └──→ [RECUSADA] (final)
     │
     └──→ [CANCELADA] (final)
```

---

## 6. Integração com Entidade Proposta

A entidade `Proposta` utiliza o validador internamente:

```php
// Transição de estado
$proposta->transicionarEstado(EstadoProposta::ENVIADA);
// Internamente usa ValidadorTransicaoEstado

// Atualização de valor
$proposta->atualizarValor(new Valor(2000.00));
// Internamente valida permissão de edição

// Verificações auxiliares
if ($proposta->isEstadoFinal()) {
    // Estado final
}

if ($proposta->podeSerEditada()) {
    // Pode editar
}
```

---

## 7. Exemplos de Uso Completo

### 7.1. Transição Válida

```php
$proposta = new Proposta($cliente, $valor);
$proposta->setId(1);

// Transição válida: RASCUNHO → ENVIADA
$proposta->transicionarEstado(EstadoProposta::ENVIADA);
// Sucesso - estado atualizado
```

### 7.2. Transição Inválida

```php
$proposta->transicionarEstado(EstadoProposta::ENVIADA);
$proposta->transicionarEstado(EstadoProposta::ACEITA);

// Tentativa de transição inválida
try {
    $proposta->transicionarEstado(EstadoProposta::RASCUNHO);
} catch (EstadoFinalImutavelException $e) {
    // Estado final não permite transições
}
```

### 7.3. Edição Permitida

```php
$proposta = new Proposta($cliente, new Valor(1000.00));

// Edição permitida (RASCUNHO)
$proposta->atualizarValor(new Valor(2000.00));
// Sucesso - valor atualizado
```

### 7.4. Edição Negada

```php
$proposta->transicionarEstado(EstadoProposta::ENVIADA);

// Tentativa de edição após envio
try {
    $proposta->atualizarValor(new Valor(3000.00));
} catch (PropostaNaoPodeSerEditadaException $e) {
    // Não pode editar proposta enviada
}
```

### 7.5. Estado Final Imutável

```php
$proposta->transicionarEstado(EstadoProposta::ENVIADA);
$proposta->transicionarEstado(EstadoProposta::ACEITA);

// Tentativa de qualquer alteração
try {
    $proposta->transicionarEstado(EstadoProposta::CANCELADA);
} catch (EstadoFinalImutavelException $e) {
    // Estado final é imutável
}

try {
    $proposta->atualizarValor(new Valor(4000.00));
} catch (EstadoFinalImutavelException $e) {
    // Estado final não permite edição
}
```

---

## 8. Benefícios do Sistema

### 8.1. Clareza
- Enum tipado com métodos descritivos
- Exceções específicas com mensagens claras
- Validador centralizado com responsabilidade única

### 8.2. Segurança
- Estados finais protegidos contra alterações
- Validação rigorosa de transições
- Exceções específicas para cada tipo de erro

### 8.3. Manutenibilidade
- Regras centralizadas no validador
- Fácil adicionar novos estados ou transições
- Código testável e isolado

### 8.4. Testabilidade
- Cada componente pode ser testado independentemente
- Exceções específicas facilitam testes
- Validador pode ser mockado facilmente

---

## 9. Estrutura de Arquivos

```
src/Domain/Proposta/
├── EstadoProposta.php                    # Enum de estados
├── ValidadorTransicaoEstado.php          # Validador centralizado
├── Proposta.php                          # Entidade (usa validador)
└── Exceptions/
    ├── EstadoFinalImutavelException.php
    ├── TransicaoEstadoInvalidaException.php
    └── PropostaNaoPodeSerEditadaException.php
```

---

## 10. Resumo

✅ **Enum tipado** com métodos auxiliares para verificação de estados
✅ **Validador centralizado** para todas as validações de estado
✅ **Exceções específicas** para cada tipo de erro
✅ **Estados finais imutáveis** protegidos contra alterações
✅ **Clareza e segurança** garantidas em todo o sistema

O sistema garante que apenas transições válidas sejam permitidas e que estados finais sejam completamente imutáveis, proporcionando segurança e clareza no controle de estados da proposta.
