# Controllers REST - API v1

## Visão Geral

Controllers REST versionados em `/api/v1` que seguem princípios de **Controllers finos**, delegando toda lógica de negócio para os Services.

---

## 1. Estrutura

```
src/Presentation/
├── Controllers/
│   └── Api/
│       └── V1/
│           ├── ClienteController.php    ✅
│           └── PropostaController.php   ✅
├── Http/
│   ├── ResponseFormatter.php            ✅
│   └── ExceptionHandler.php             ✅
└── Router.php                           ✅
```

---

## 2. ResponseFormatter

**Arquivo**: `src/Presentation/Http/ResponseFormatter.php`

Padroniza todas as respostas da API.

### 2.1. Métodos

- `success($data, $statusCode, $message)` - Resposta de sucesso
- `error($message, $statusCode, $errors)` - Resposta de erro
- `validationError($errors)` - Erro de validação (422)
- `notFound($resource)` - Recurso não encontrado (404)
- `conflict($message)` - Conflito (409)
- `paginated($data, $page, $perPage, $total)` - Resposta paginada

### 2.2. Formato Padrão

**Sucesso**:
```json
{
  "success": true,
  "data": { ... },
  "message": "Mensagem opcional"
}
```

**Erro**:
```json
{
  "success": false,
  "message": "Mensagem de erro",
  "errors": { ... } // Opcional
}
```

---

## 3. ExceptionHandler

**Arquivo**: `src/Presentation/Http/ExceptionHandler.php`

Converte exceções de domínio em respostas HTTP apropriadas.

### 3.1. Mapeamento de Exceções

| Exceção | Status Code | Descrição |
|---------|-------------|-----------|
| `EstadoFinalImutavelException` | 409 | Estado final não pode ser alterado |
| `TransicaoEstadoInvalidaException` | 422 | Transição de estado inválida |
| `PropostaNaoPodeSerEditadaException` | 422 | Proposta não pode ser editada |
| `DomainException` (versão incorreta) | 409 | Optimistic lock - conflito de versão |
| `DomainException` (não encontrado) | 404 | Recurso não encontrado |
| `DomainException` (outros) | 400 | Erro de domínio |
| `InvalidArgumentException` | 422 | Erro de validação |
| Outros | 500 | Erro interno do servidor |

---

## 4. ClienteController

**Arquivo**: `src/Presentation/Controllers/Api/V1/ClienteController.php`

### 4.1. Endpoints

#### POST /api/v1/clientes

Cria um novo cliente.

**Headers**:
- `Idempotency-Key` (opcional): Chave de idempotência

**Body**:
```json
{
  "nome": "João Silva",
  "email": "joao@example.com",
  "documento": "123.456.789-00"
}
```

**Resposta (201 Created)**:
```json
{
  "success": true,
  "data": {
    "id": 1,
    "nome": "João Silva",
    "email": "joao@example.com",
    "documento": "123.456.789-00",
    "idempotencia_key": "client-001",
    "created_at": "2024-01-15 10:30:00",
    "updated_at": null
  },
  "message": "Cliente criado com sucesso"
}
```

**Status Codes**:
- `201` - Cliente criado
- `422` - Erro de validação
- `409` - Cliente já existe (email/documento duplicado)

#### GET /api/v1/clientes/{id}

Busca cliente por ID.

**Resposta (200 OK)**:
```json
{
  "success": true,
  "data": {
    "id": 1,
    "nome": "João Silva",
    ...
  }
}
```

**Status Codes**:
- `200` - Cliente encontrado
- `404` - Cliente não encontrado

---

## 5. PropostaController

**Arquivo**: `src/Presentation/Controllers/Api/V1/PropostaController.php`

### 5.1. Endpoints

#### POST /api/v1/propostas

Cria uma nova proposta em estado RASCUNHO (DRAFT).

**Headers**:
- `Idempotency-Key` (opcional): Chave de idempotência

**Body**:
```json
{
  "cliente_id": 1,
  "valor": 1500.00,
  "usuario": "admin"
}
```

**Resposta (201 Created)**:
```json
{
  "success": true,
  "data": {
    "id": 1,
    "cliente": {
      "nome": "João Silva",
      "email": "joao@example.com",
      "documento": "123.456.789-00"
    },
    "valor": 1500.0,
    "estado": "rascunho",
    "versao": 1,
    "idempotencia_key": "proposta-001",
    "created_at": "2024-01-15 10:30:00",
    "updated_at": null
  },
  "message": "Proposta criada com sucesso"
}
```

**Status Codes**:
- `201` - Proposta criada (nova)
- `200` - Proposta retornada (idempotência)
- `422` - Erro de validação
- `404` - Cliente não encontrado

#### GET /api/v1/propostas

Lista propostas com paginação.

**Query Params**:
- `pagina` (opcional, default: 1)
- `por_pagina` (opcional, default: 50)

**Resposta (200 OK)**:
```json
{
  "success": true,
  "data": [ ... ],
  "pagination": {
    "page": 1,
    "per_page": 50,
    "total": 100,
    "total_pages": 2
  }
}
```

#### GET /api/v1/propostas/{id}

Busca proposta por ID.

**Resposta (200 OK)**:
```json
{
  "success": true,
  "data": {
    "id": 1,
    "estado": "rascunho",
    ...
  }
}
```

**Status Codes**:
- `200` - Proposta encontrada
- `404` - Proposta não encontrada

#### POST /api/v1/propostas/{id}/submeter

Submete uma proposta (RASCUNHO → ENVIADA).

**Headers**:
- `Idempotency-Key` (opcional): Chave de idempotência

**Body**:
```json
{
  "versao": 1,
  "usuario": "admin"
}
```

**Resposta (200 OK)**:
```json
{
  "success": true,
  "data": {
    "id": 1,
    "estado": "enviada",
    "versao": 2,
    ...
  },
  "message": "Proposta submetida com sucesso"
}
```

**Status Codes**:
- `200` - Proposta submetida
- `409` - Conflito (versão incorreta ou estado final)
- `422` - Erro de validação ou transição inválida
- `404` - Proposta não encontrada

#### POST /api/v1/propostas/{id}/aprovar

Aprova uma proposta (ENVIADA → ACEITA).

**Body**:
```json
{
  "versao": 2,
  "usuario": "cliente"
}
```

**Resposta (200 OK)**:
```json
{
  "success": true,
  "data": {
    "id": 1,
    "estado": "aceita",
    "versao": 3,
    ...
  },
  "message": "Proposta aprovada com sucesso"
}
```

**Status Codes**:
- `200` - Proposta aprovada
- `409` - Conflito (versão incorreta)
- `422` - Transição inválida (proposta não está em ENVIADA)
- `404` - Proposta não encontrada

#### POST /api/v1/propostas/{id}/rejeitar

Rejeita uma proposta (ENVIADA → RECUSADA).

**Body**:
```json
{
  "versao": 2,
  "usuario": "cliente"
}
```

**Status Codes**: Mesmos de aprovar

#### POST /api/v1/propostas/{id}/cancelar

Cancela uma proposta (RASCUNHO/ENVIADA → CANCELADA).

**Body**:
```json
{
  "versao": 1,
  "usuario": "admin"
}
```

**Status Codes**:
- `200` - Proposta cancelada
- `409` - Conflito (versão incorreta ou estado final)
- `422` - Transição inválida
- `404` - Proposta não encontrado

---

## 6. HTTP Status Codes

### 6.1. Sucesso

| Código | Descrição | Uso |
|--------|-----------|-----|
| `200` | OK | GET, PUT, PATCH, operações bem-sucedidas |
| `201` | Created | POST - recurso criado |

### 6.2. Erro do Cliente

| Código | Descrição | Uso |
|--------|-----------|-----|
| `400` | Bad Request | Erro genérico de requisição |
| `404` | Not Found | Recurso não encontrado |
| `405` | Method Not Allowed | Método HTTP não permitido |
| `409` | Conflict | Conflito (optimistic lock, estado final) |
| `422` | Unprocessable Entity | Erro de validação ou regra de negócio |

### 6.3. Erro do Servidor

| Código | Descrição | Uso |
|--------|-----------|-----|
| `500` | Internal Server Error | Erro interno do servidor |

---

## 7. Padronização de Respostas

### 7.1. Resposta de Sucesso

```json
{
  "success": true,
  "data": { ... },
  "message": "Mensagem opcional"
}
```

### 7.2. Resposta de Erro

```json
{
  "success": false,
  "message": "Mensagem de erro",
  "errors": {
    "campo": "Erro específico do campo"
  }
}
```

### 7.3. Resposta Paginada

```json
{
  "success": true,
  "data": [ ... ],
  "pagination": {
    "page": 1,
    "per_page": 50,
    "total": 100,
    "total_pages": 2
  }
}
```

---

## 8. Headers Padrão

Todas as respostas incluem:

```
Content-Type: application/json; charset=utf-8
X-API-Version: v1
```

---

## 9. Principios dos Controllers

### 9.1. Controllers Finos

- ✅ Apenas coordenam requisições HTTP
- ✅ Delegam lógica para Services
- ✅ Formatam respostas
- ✅ Tratam exceções

### 9.2. Responsabilidades

**✅ Fazem**:
- Extraem dados da requisição
- Validam formato de entrada
- Chamam Services
- Formatam respostas
- Tratam exceções

**❌ Não Fazem**:
- Lógica de negócio
- Acesso a banco de dados
- Validações complexas
- Transformações de dados complexas

### 9.3. Exemplo

```php
public function criar(array $request): array
{
    try {
        // 1. Valida formato de entrada
        $this->validarCriacaoCliente($request);
        
        // 2. Extrai dados
        $idempotencyKey = $this->extrairIdempotencyKey($request);
        
        // 3. Delega para Service
        $cliente = $this->clienteService->criar(
            $request['nome'],
            $request['email'],
            $request['documento'] ?? null,
            $idempotencyKey
        );
        
        // 4. Formata resposta
        return ResponseFormatter::success(
            $this->serializarCliente($cliente),
            201
        );
    } catch (\Throwable $e) {
        // 5. Trata exceções
        return ExceptionHandler::handle($e)[0];
    }
}
```

---

## 10. Endpoints Completos

### 10.1. Clientes

- `POST /api/v1/clientes` - Criar cliente
- `GET /api/v1/clientes/{id}` - Buscar cliente

### 10.2. Propostas

- `POST /api/v1/propostas` - Criar proposta
- `GET /api/v1/propostas` - Listar propostas
- `GET /api/v1/propostas/{id}` - Buscar proposta
- `POST /api/v1/propostas/{id}/submeter` - Submeter proposta
- `POST /api/v1/propostas/{id}/aprovar` - Aprovar proposta
- `POST /api/v1/propostas/{id}/rejeitar` - Rejeitar proposta
- `POST /api/v1/propostas/{id}/cancelar` - Cancelar proposta

---

## 11. Idempotência

Todos os endpoints de criação/submissão suportam `Idempotency-Key` via:

1. **Header HTTP**: `Idempotency-Key: key-123`
2. **Body (fallback)**: `{"idempotency_key": "key-123"}`

---

## 12. Resumo

✅ **Controllers REST versionados** em `/api/v1`
✅ **Endpoints completos** implementados
✅ **HTTP status codes** corretos
✅ **Respostas padronizadas** via ResponseFormatter
✅ **Erros padronizados** via ExceptionHandler
✅ **Controllers finos** (apenas coordenam)
✅ **Idempotência** suportada
✅ **Validação de entrada** nos controllers
✅ **Tratamento de exceções** centralizado

A API está pronta para uso e segue todas as boas práticas de REST.
