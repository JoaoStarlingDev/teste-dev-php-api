# Exemplos de Uso da API

## Criar Proposta

```bash
curl -X POST http://localhost:8000/api/v1/propostas \
  -H "Content-Type: application/json" \
  -d '{
    "cliente": {
      "nome": "João Silva",
      "email": "joao@example.com",
      "documento": "123.456.789-00"
    },
    "valor": 1500.00,
    "idempotencia_key": "proposta-001",
    "usuario": "admin"
  }'
```

**Resposta:**
```json
{
  "status": "success",
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
    "criado_em": "2024-01-15 10:30:00",
    "atualizado_em": null
  }
}
```

## Buscar Proposta

```bash
curl http://localhost:8000/api/v1/propostas/1
```

## Listar Propostas

```bash
curl "http://localhost:8000/api/v1/propostas?pagina=1&por_pagina=10"
```

## Atualizar Proposta (Optimistic Lock)

```bash
curl -X PATCH http://localhost:8000/api/v1/propostas/1 \
  -H "Content-Type: application/json" \
  -d '{
    "valor": 2000.00,
    "versao": 1,
    "usuario": "admin"
  }'
```

**Se a versão estiver desatualizada:**
```json
{
  "status": "error",
  "message": "Proposta foi modificada por outro processo. Versão atual: 2"
}
```

## Transicionar Estado

```bash
curl -X POST http://localhost:8000/api/v1/propostas/1/transicionar-estado \
  -H "Content-Type: application/json" \
  -d '{
    "estado": "enviada",
    "versao": 2,
    "usuario": "admin"
  }'
```

## Fluxo Completo

1. **Criar proposta em rascunho:**
```bash
curl -X POST http://localhost:8000/api/v1/propostas \
  -H "Content-Type: application/json" \
  -d '{
    "cliente": {
      "nome": "Maria Santos",
      "email": "maria@example.com"
    },
    "valor": 3000.00,
    "idempotencia_key": "proposta-maria-001"
  }'
```

2. **Atualizar valor (ainda em rascunho):**
```bash
curl -X PATCH http://localhost:8000/api/v1/propostas/1 \
  -H "Content-Type: application/json" \
  -d '{
    "valor": 3500.00,
    "versao": 1
  }'
```

3. **Enviar proposta:**
```bash
curl -X POST http://localhost:8000/api/v1/propostas/1/transicionar-estado \
  -H "Content-Type: application/json" \
  -d '{
    "estado": "enviada",
    "versao": 2
  }'
```

4. **Aceitar proposta:**
```bash
curl -X POST http://localhost:8000/api/v1/propostas/1/transicionar-estado \
  -H "Content-Type: application/json" \
  -d '{
    "estado": "aceita",
    "versao": 3
  }'
```

## Consultar Auditoria

```bash
# Todas as auditorias de propostas
curl http://localhost:8000/api/v1/auditoria/Proposta

# Auditorias de uma proposta específica
curl http://localhost:8000/api/v1/auditoria/Proposta/1
```

**Resposta:**
```json
{
  "status": "success",
  "data": [
    {
      "id": 1,
      "entidade_tipo": "Proposta",
      "entidade_id": 1,
      "acao": "CRIAR",
      "dados_anteriores": [],
      "dados_novos": {
        "id": 1,
        "cliente": {...},
        "valor": 1500.0,
        "estado": "rascunho",
        "versao": 1
      },
      "usuario": "admin",
      "ocorrido_em": "2024-01-15 10:30:00"
    }
  ]
}
```

## Teste de Idempotência

Enviar a mesma requisição duas vezes com a mesma `idempotencia_key`:

```bash
# Primeira requisição
curl -X POST http://localhost:8000/api/v1/propostas \
  -H "Content-Type: application/json" \
  -d '{
    "cliente": {
      "nome": "Teste",
      "email": "teste@example.com"
    },
    "valor": 1000.00,
    "idempotencia_key": "teste-idempotencia-001"
  }'

# Segunda requisição (mesma chave) - retorna a mesma proposta
curl -X POST http://localhost:8000/api/v1/propostas \
  -H "Content-Type: application/json" \
  -d '{
    "cliente": {
      "nome": "Teste",
      "email": "teste@example.com"
    },
    "valor": 1000.00,
    "idempotencia_key": "teste-idempotencia-001"
  }'
```

A segunda requisição retornará a mesma proposta criada na primeira, garantindo idempotência.
