# API REST - Gestão de Propostas

API REST versionada para sistema de Gestão de Propostas, implementada seguindo **Arquitetura Limpa**, **regras de negócio rigorosas** e **boas práticas de engenharia de software**.

---

## 📋 Visão Geral do Projeto

Este projeto implementa uma API REST completa para gerenciamento de propostas comerciais, com foco em:

- ✅ **Controle de Estados**: Máquina de estados finita (FSM) para gerenciar o ciclo de vida das propostas
- ✅ **Auditoria Automática**: Registro completo de todas as operações com rastreabilidade total
- ✅ **Idempotência**: Suporte a chaves de idempotência para operações seguras em cenários de retry
- ✅ **Optimistic Lock**: Controle de concorrência através de versionamento
- ✅ **Arquitetura Limpa**: Separação clara entre camadas (Domain, Application, Infrastructure, Presentation)
- ✅ **Testabilidade**: Código totalmente testável com cobertura de testes unitários e de integração

### Características Principais

- **API REST versionada** (`/api/v1`)
- **Controle rigoroso de estados** e transições válidas
- **Auditoria completa** com payload em JSON
- **Idempotência** para criação e submissão de propostas
- **Controle de concorrência** via optimistic lock
- **Filtros, ordenação e paginação** obrigatória
- **Código limpo** seguindo princípios SOLID

---

## 📦 Requisitos

### Requisitos de Sistema

- **PHP**: >= 8.1
- **Composer**: >= 2.0
- **Extensões PHP**:
  - `json`
  - `mbstring`
  - `openssl`
  - `pdo` (para futura integração com banco de dados)

### Dependências

#### Produção

- `psr/http-message`: ^2.0
- `psr/http-server-handler`: ^1.0
- `psr/http-server-middleware`: ^1.0
- `psr/container`: ^2.0

#### Desenvolvimento

- `phpunit/phpunit`: ^10.0

### Dependências Opcionais (Futuras)

- **CodeIgniter 4**: Para implementação com banco de dados
- **MySQL/PostgreSQL**: Para persistência de dados
- **Redis**: Para cache e idempotência distribuída

---

## 🚀 Como Rodar o Projeto

### 1. Instalação

Clone o repositório e instale as dependências:

```bash
# Clone o repositório
git clone <repository-url>
cd TesteDev

# Instale as dependências
composer install
```

### 2. Configuração

O projeto não requer configuração adicional para execução em modo de desenvolvimento. Os repositórios estão implementados em memória para demonstração.

**Nota**: Para produção, será necessário configurar banco de dados e substituir os repositórios em memória por implementações com persistência.

### 3. Execução

#### Opção 1: PHP Built-in Server (Recomendado para desenvolvimento)

```bash
php -S localhost:8000 -t public
```

#### Opção 2: Servidor Web (Apache/Nginx)

Configure seu servidor web para apontar para o diretório `public/`:

**Apache (.htaccess)**:
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]
```

**Nginx**:
```nginx
server {
    listen 80;
    server_name localhost;
    root /caminho/para/projeto/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

### 4. Testando a API

Após iniciar o servidor, teste a API:

```bash
# Criar uma proposta
curl -X POST http://localhost:8000/api/v1/propostas \
  -H "Content-Type: application/json" \
  -d '{
    "cliente_id": 1,
    "valor": 1500.00,
    "idempotency_key": "teste-123",
    "usuario": "admin"
  }'

# Listar propostas
curl http://localhost:8000/api/v1/propostas?pagina=1&por_pagina=10

# Buscar proposta por ID
curl http://localhost:8000/api/v1/propostas/1
```

---

## 🧪 Como Rodar os Testes

### Executar Todos os Testes

```bash
vendor/bin/phpunit
```

### Executar Testes Específicos

```bash
# Testes de domínio
vendor/bin/phpunit tests/Domain

# Testes de serviços
vendor/bin/phpunit tests/Application/Services

# Testes de repositórios
vendor/bin/phpunit tests/Infrastructure/Repository

# Arquivo específico
vendor/bin/phpunit tests/Application/Services/PropostaServiceTest.php
```

### Executar Testes com Cobertura (se configurado)

```bash
vendor/bin/phpunit --coverage-text
vendor/bin/phpunit --coverage-html coverage/
```

### Estrutura de Testes

```
tests/
├── Domain/
│   └── Proposta/
│       └── PropostaTest.php          # Testes de entidade
├── Application/
│   └── Services/
│       └── PropostaServiceTest.php   # Testes de integração (transições, idempotência, optimistic lock)
└── Infrastructure/
    └── Repository/
        └── PropostaRepositoryTest.php # Testes de busca (filtros, paginação, ordenação)
```

### Tipos de Testes Implementados

1. **Transições de Estado**:
   - ✅ Transições válidas (RASCUNHO → ENVIADA → ACEITA)
   - ✅ Transições inválidas (estados finais imutáveis)
   - ✅ Transições proibidas (pular etapas)

2. **Idempotência**:
   - ✅ Criação de proposta idempotente
   - ✅ Submissão de proposta idempotente

3. **Conflito de Versão (Optimistic Lock)**:
   - ✅ Rejeição de versão antiga
   - ✅ Rejeição de versão futura
   - ✅ Simulação de concorrência

4. **Busca com Filtros e Paginação**:
   - ✅ Filtro por cliente
   - ✅ Filtro por estado
   - ✅ Filtros combinados
   - ✅ Paginação (primeira, última, páginas parciais)
   - ✅ Ordenação (ASC, DESC, múltiplos campos)
   - ✅ Combinações complexas

Para mais detalhes sobre os testes, consulte [`TESTES_OBJETIVOS.md`](TESTES_OBJETIVOS.md).

---

## 🏗️ Decisões Técnicas Importantes

### 1. Arquitetura Limpa (Clean Architecture)

O projeto segue **Arquitetura Limpa** com separação clara entre camadas:

```
src/
├── Domain/                    # Regras de negócio puras
│   ├── Proposta/
│   ├── Auditoria/
│   ├── Cliente/
│   └── Idempotencia/
├── Application/               # Casos de uso e orquestração
│   └── Services/
├── Infrastructure/            # Implementações concretas
│   └── Repository/
└── Presentation/              # Interface HTTP
    ├── Controllers/
    └── Http/
```

**Por quê**: Separa responsabilidades, facilita testes, torna o código independente de frameworks e facilita manutenção.

**Benefícios**:
- ✅ Domain testável sem dependências externas
- ✅ Fácil substituição de implementações (ex: banco de dados)
- ✅ Regras de negócio isoladas e reutilizáveis

---

### 2. Máquina de Estados Finita (FSM)

O controle de estados é implementado via **FSM** com enum tipado (`EstadoProposta`):

**Estados**:
- `RASCUNHO` (inicial) → Permite edição
- `ENVIADA` (intermediário) → Aguarda resposta
- `ACEITA`, `RECUSADA`, `CANCELADA` (finais) → Imutáveis

**Transições Válidas**:
- `RASCUNHO` → `ENVIADA` ou `CANCELADA`
- `ENVIADA` → `ACEITA`, `RECUSADA` ou `CANCELADA`
- Estados finais → ❌ Nenhuma transição

**Por quê**: Garante integridade do estado, previne transições inválidas e torna as regras de negócio explícitas e testáveis.

**Implementação**: `ValidadorTransicaoEstado` valida todas as transições, lançando exceções específicas para casos inválidos.

---

### 3. Value Objects

Uso de **Value Objects** para garantir invariantes:

- `Valor`: Garante valores positivos e formatação correta
- `Cliente`: Valida dados do cliente (nome, email, documento)
- `IdempotenciaKey`: Garante formato válido da chave

**Por quê**: Encapsula validações, previne estados inválidos e torna o código mais expressivo e seguro.

**Exemplo**:
```php
$valor = new Valor(1000.0); // ✅ Válido
$valor = new Valor(-100);   // ❌ Lança exceção
```

---

### 4. Optimistic Lock (Versionamento)

Controle de concorrência via **versionamento**:

- Cada proposta possui campo `versao`
- Versão é incrementada a cada alteração
- Operações validam versão esperada antes de executar
- Se versão não corresponder, operação é rejeitada

**Por quê**: Previne sobrescrita acidental de dados em cenários de concorrência, sem bloquear operações (diferente de pessimistic lock).

**Implementação**: `PropostaService` valida versão antes de cada operação de atualização/transição.

**Exemplo**:
```php
// Cliente lê proposta (versão 1)
$proposta = $service->buscarPorId(1);

// Outro processo atualiza (versão 1 → 2)
$service->submeterProposta(1, 1);

// Cliente tenta atualizar com versão antiga (falha)
$service->aprovarProposta(1, 1); // ❌ Lança exceção: "versão esperada: 2"
```

---

### 5. Idempotência via Idempotency-Key

Suporte a **Idempotency-Key** para operações seguras:

- Cliente envia `idempotency_key` única por requisição
- Sistema verifica se operação já foi executada
- Se sim, retorna resultado anterior (sem processar novamente)

**Por quê**: Previne duplicação de dados em cenários de retry, requisições duplicadas ou problemas de rede.

**Implementação**: `IdempotenciaOperacaoRepository` armazena operações por chave e tipo.

**Exemplo**:
```php
// Primeira requisição
$proposta1 = $service->criarProposta(
    clienteId: 1,
    valor: 1000.0,
    idempotenciaKey: 'key-123'
);

// Requisição duplicada (mesma chave)
$proposta2 = $service->criarProposta(
    clienteId: 1,
    valor: 1000.0,
    idempotenciaKey: 'key-123'
);

// $proposta1 === $proposta2 (mesma proposta, sem duplicação)
```

---

### 6. Auditoria Automática com Eventos Tipados

Sistema de **auditoria automática** com eventos tipados:

**Eventos**:
- `CREATED`: Entidade criada
- `UPDATED_FIELDS`: Campos atualizados (com diff automático)
- `STATUS_CHANGED`: Estado alterado
- `DELETED_LOGICAL`: Exclusão lógica

**Payload em JSON**: Dados armazenados em JSON para rastreabilidade completa e compatibilidade futura.

**Por quê**: Rastreabilidade completa de todas as operações, cumprimento de requisitos de compliance e facilidade de análise histórica.

**Implementação**: `AuditoriaService` registra eventos automaticamente em todas as operações críticas.

**Exemplo**:
```json
{
  "evento": "STATUS_CHANGED",
  "payload_anterior": "{\"estado\":\"rascunho\",...}",
  "payload_novo": "{\"estado\":\"enviada\",...}",
  "usuario": "admin",
  "ocorrido_em": "2024-01-15 10:00:00"
}
```

---

### 7. Repository Pattern

Uso do **Repository Pattern** para abstração de persistência:

- Interfaces no Domain (`PropostaRepositoryInterface`)
- Implementações na Infrastructure (`PropostaRepository`)
- Services dependem apenas de interfaces

**Por quê**: Facilita testes (mock de repositórios), permite trocar implementações sem afetar regras de negócio e prepara para migração para banco de dados.

**Implementação Atual**: Repositórios em memória para demonstração.

**Migração Futura**: Substituir por implementações com CodeIgniter 4 Models.

---

### 8. Paginação Obrigatória

**Paginação obrigatória** em todas as listagens:

- `pagina` e `por_pagina` são obrigatórios
- `por_pagina` limitado entre 1-100
- Resposta inclui `total` para cálculo de páginas

**Por quê**: Previne sobrecarga do servidor, melhora performance e garante respostas consistentes.

**Implementação**: `PropostaCriteria` valida parâmetros e `PropostaRepository` aplica paginação.

---

### 9. Filtros com Índices (O(1))

Filtros implementados com **índices** para performance:

- `clienteIndex[clienteId]` → IDs de propostas
- `estadoIndex[estado]` → IDs de propostas
- Busca O(1) em vez de O(n)

**Por quê**: Performance em consultas grandes, escalabilidade e preparação para banco de dados (índices SQL).

**Implementação**: Índices mantidos automaticamente ao salvar propostas.

---

### 10. Batch Loading (Evitar N+1 Queries)

**Batch loading** para evitar N+1 queries:

- Coleta IDs únicos de clientes
- Carrega todos os clientes em uma operação
- Reduz de 1+N queries para 2 queries

**Por quê**: Performance em listagens com muitos registros e preparação para banco de dados (JOIN SQL).

**Implementação Futura**: `SELECT p.*, c.* FROM propostas p INNER JOIN clientes c ON p.cliente_id = c.id`

---

## 📚 Documentação Adicional

O projeto inclui documentação detalhada sobre cada aspecto:

- [`MODELAGEM_DOMINIO.md`](MODELAGEM_DOMINIO.md) - Modelagem do domínio e FSM
- [`MODELO_RELACIONAL.md`](MODELO_RELACIONAL.md) - Modelo de dados relacional
- [`ARQUITETURA_CAMADAS.md`](ARQUITETURA_CAMADAS.md) - Arquitetura em camadas
- [`VALIDACAO_ESTADOS.md`](VALIDACAO_ESTADOS.md) - Validação de estados e transições
- [`IDEMPOTENCIA.md`](IDEMPOTENCIA.md) - Implementação de idempotência
- [`AUDITORIA_AUTOMATICA.md`](AUDITORIA_AUTOMATICA.md) - Sistema de auditoria automática
- [`SERVICES.md`](SERVICES.md) - Documentação dos services
- [`CONTROLLERS_REST.md`](CONTROLLERS_REST.md) - Documentação dos controllers
- [`FILTROS_ORDENACAO_PAGINACAO.md`](FILTROS_ORDENACAO_PAGINACAO.md) - Filtros, ordenação e paginação
- [`TESTES_OBJETIVOS.md`](TESTES_OBJETIVOS.md) - Objetivos e explicação dos testes
- [`EXEMPLOS.md`](EXEMPLOS.md) - Exemplos de uso da API

---

## 🗺️ Endpoints da API

### Base URL

```
/api/v1
```

### Propostas

#### Criar Proposta

```http
POST /api/v1/propostas
Content-Type: application/json

{
  "cliente_id": 1,
  "valor": 1500.00,
  "idempotency_key": "unique-key-123",
  "usuario": "admin"
}
```

#### Listar Propostas (com filtros e paginação obrigatória)

```http
GET /api/v1/propostas?pagina=1&por_pagina=10&cliente_id=1&estado=enviada&ordenar_por=valor&direcao=ASC
```

#### Buscar Proposta por ID

```http
GET /api/v1/propostas/{id}
```

#### Submeter Proposta

```http
POST /api/v1/propostas/{id}/submeter
Content-Type: application/json

{
  "versao": 1,
  "idempotency_key": "submit-123",
  "usuario": "cliente"
}
```

#### Aprovar Proposta

```http
POST /api/v1/propostas/{id}/aprovar
Content-Type: application/json

{
  "versao": 2,
  "usuario": "admin"
}
```

#### Rejeitar Proposta

```http
POST /api/v1/propostas/{id}/rejeitar
Content-Type: application/json

{
  "versao": 2,
  "usuario": "admin"
}
```

#### Cancelar Proposta

```http
POST /api/v1/propostas/{id}/cancelar
Content-Type: application/json

{
  "versao": 1,
  "usuario": "admin"
}
```

### Auditoria

#### Buscar Auditoria

```http
GET /api/v1/auditoria/{entidadeTipo}/{entidadeId?}
```

**Exemplos**:
- `GET /api/v1/auditoria/Proposta` - Todas as auditorias de propostas
- `GET /api/v1/auditoria/Proposta/1` - Auditorias da proposta ID 1

---

## 🔐 Segurança e Validações

### Validações Implementadas

- ✅ Valores monetários devem ser positivos
- ✅ Paginação obrigatória e validada (1-100 itens por página)
- ✅ Versão obrigatória para operações de atualização
- ✅ Transições de estado validadas pela FSM
- ✅ Estados finais imutáveis

### Recomendações para Produção

- Implementar autenticação/autorização (JWT, OAuth2)
- Rate limiting para prevenir abuso
- Validação de entrada mais rigorosa
- Sanitização de dados
- Logs de segurança
- HTTPS obrigatório

---

## 🚧 Próximos Passos (Melhorias Futuras)

- [ ] Integração com banco de dados (CodeIgniter 4)
- [ ] Autenticação e autorização
- [ ] Cache distribuído (Redis) para idempotência
- [ ] Logs estruturados
- [ ] Documentação OpenAPI/Swagger
- [ ] Rate limiting
- [ ] Health check endpoint
- [ ] Métricas e monitoramento

---

## 📝 Licença

Este projeto foi desenvolvido como teste técnico.

---

## 👨‍💻 Autor

Desenvolvido seguindo boas práticas de engenharia de software e arquitetura limpa.

---

**Versão**: 1.0.0  
**Última atualização**: 2024
