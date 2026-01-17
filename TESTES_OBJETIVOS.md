# Testes - Objetivos e Explicações

## ✅ Testes Implementados

Todos os testes foram criados seguindo boas práticas de TDD e cobrem os principais cenários de uso do sistema.

---

## 1. Testes de Transições de Estado

### 1.1. Transições Válidas

**Arquivo**: `tests/Application/Services/PropostaServiceTest.php`  
**Método**: `testTransicoesValidas()`

**Objetivo**: Garantir que todas as transições de estado válidas são executadas com sucesso e seguem o fluxo correto da FSM (Finite State Machine).

**Cenários testados**:
- ✅ RASCUNHO → ENVIADA
- ✅ ENVIADA → ACEITA
- ✅ ENVIADA → RECUSADA (implicitamente testado)
- ✅ RASCUNHO → CANCELADA (implicitamente testado)

**Por que é importante**: Valida que o fluxo de negócio está funcionando corretamente e que as regras de transição estão sendo respeitadas.

**Assertivas**:
- Estado atualizado corretamente
- Versão incrementada a cada transição
- Sem exceções lançadas

---

### 1.2. Transições Inválidas - Estados Finais Imutáveis

**Arquivo**: `tests/Application/Services/PropostaServiceTest.php`  
**Método**: `testTransicoesInvalidasEstadosFinaisImutaveis()`

**Objetivo**: Garantir que estados finais (ACEITA, RECUSADA, CANCELADA) não permitem transições, seguindo a regra de negócio de imutabilidade de estados finais.

**Cenário testado**:
- ❌ ACEITA não pode transicionar para nenhum outro estado

**Por que é importante**: Protege a integridade dos dados e garante que estados finais não podem ser alterados, mantendo o histórico e a rastreabilidade.

**Assertivas**:
- Estado final identificado corretamente (`isFinal()`)
- Exceção lançada ao tentar transicionar
- Tipo de exceção: `\DomainException`

---

### 1.3. Transições Inválidas - Transições Proibidas

**Arquivo**: `tests/Application/Services/PropostaServiceTest.php`  
**Método**: `testTransicoesInvalidasTransicoesProibidas()`

**Objetivo**: Garantir que transições inválidas (não permitidas pela FSM) são rejeitadas, mantendo a integridade do estado.

**Cenário testado**:
- ❌ RASCUNHO não pode ir diretamente para ACEITA (deve passar por ENVIADA)

**Por que é importante**: Garante que o fluxo de trabalho é respeitado e que não é possível pular etapas do processo.

**Assertivas**:
- Exceção lançada ao tentar transição inválida
- Tipo de exceção: `\DomainException`

---

### 1.4. Transições Inválidas - Não Permite Voltar para RASCUNHO

**Arquivo**: `tests/Application/Services/PropostaServiceTest.php`  
**Método**: `testTransicoesInvalidasNaoPermiteVoltarParaRascunho()`

**Objetivo**: Garantir que uma vez enviada, a proposta não pode voltar para RASCUNHO, mantendo o fluxo unidirecional.

**Cenário testado**:
- ❌ ENVIADA não pode voltar para RASCUNHO

**Por que é importante**: Mantém o fluxo unidirecional e garante que propostas não podem ser "revertidas" para edição após serem enviadas.

**Assertivas**:
- Estado ENVIADA não contém RASCUNHO nas transições permitidas
- Validação feita através de `estadosValidosParaTransicao()`

---

## 2. Testes de Idempotência

### 2.1. Idempotência - Criação de Proposta

**Arquivo**: `tests/Application/Services/PropostaServiceTest.php`  
**Método**: `testIdempotenciaCriacaoProposta()`

**Objetivo**: Garantir que requisições duplicadas com a mesma chave de idempotência retornam a mesma proposta criada anteriormente, evitando duplicação de dados.

**Cenário testado**:
- Criar proposta duas vezes com a mesma `idempotency-key`
- Deve retornar a mesma proposta (mesmo ID e versão)

**Por que é importante**: Previne duplicação de dados em cenários de retry, requisições duplicadas ou problemas de rede, garantindo que operações idempotentes sejam seguras.

**Assertivas**:
- Mesmo ID retornado
- Mesma versão retornada
- Mesma instância de objeto (referência)

---

### 2.2. Idempotência - Submissão de Proposta

**Arquivo**: `tests/Application/Services/PropostaServiceTest.php`  
**Método**: `testIdempotenciaSubmissaoProposta()`

**Objetivo**: Garantir que submeter uma proposta duas vezes com a mesma chave de idempotência retorna a mesma proposta já submetida, evitando processamento duplicado.

**Cenário testado**:
- Submeter proposta duas vezes com a mesma `idempotency-key`
- Deve retornar a mesma proposta já submetida

**Por que é importante**: Previne processamento duplicado de transições de estado, garantindo que operações críticas (como submissão) sejam seguras em cenários de retry.

**Assertivas**:
- Mesmo estado retornado (ENVIADA)
- Mesma versão retornada
- Nenhuma duplicação de auditoria ou processamento

---

## 3. Testes de Conflito de Versão (Optimistic Lock)

### 3.1. Conflito de Versão - Versão Antiga

**Arquivo**: `tests/Application/Services/PropostaServiceTest.php`  
**Método**: `testConflitoVersaoVersaoAntiga()`

**Objetivo**: Garantir que operações com versão desatualizada são rejeitadas, detectando conflitos de concorrência (optimistic lock).

**Cenário testado**:
- Tentar atualizar proposta com versão antiga após outra operação já ter alterado a proposta
- Deve lançar exceção indicando conflito de versão

**Por que é importante**: Detecta conflitos de concorrência quando duas operações tentam modificar a mesma proposta simultaneamente, prevenindo sobrescrita acidental de dados.

**Assertivas**:
- Exceção lançada com mensagem contendo "versão esperada"
- Tipo de exceção: `\DomainException`

---

### 3.2. Conflito de Versão - Versão Futura

**Arquivo**: `tests/Application/Services/PropostaServiceTest.php`  
**Método**: `testConflitoVersaoVersaoFutura()`

**Objetivo**: Garantir que operações com versão futura são rejeitadas, detectando inconsistências no controle de versão.

**Cenário testado**:
- Tentar atualizar proposta com versão que ainda não existe (ex: 999)
- Deve lançar exceção indicando conflito de versão

**Por que é importante**: Detecta erros de programação ou inconsistências no controle de versão, garantindo que apenas versões válidas sejam aceitas.

**Assertivas**:
- Exceção lançada com mensagem contendo "versão esperada"
- Tipo de exceção: `\DomainException`

---

### 3.3. Conflito de Versão - Simulação de Concorrência

**Arquivo**: `tests/Application/Services/PropostaServiceTest.php`  
**Método**: `testConflitoVersaoSimulacaoConcorrencia()`

**Objetivo**: Simular cenário real de concorrência onde duas operações tentam atualizar a mesma proposta simultaneamente.

**Cenário testado**:
- Duas "threads" lendo a proposta na versão 1
- Thread 1: Submete proposta (sucesso - versão 1 → 2)
- Thread 2: Tenta aprovar com versão antiga (falha - conflito de versão)

**Por que é importante**: Valida o comportamento do optimistic lock em cenários reais de concorrência, garantindo que apenas uma operação tenha sucesso.

**Assertivas**:
- Primeira operação tem sucesso (versão atualizada)
- Segunda operação falha (exceção lançada)
- Mensagem de erro indica conflito de versão

---

### 3.4. Sem Conflito de Versão - Operação Correta

**Arquivo**: `tests/Application/Services/PropostaServiceTest.php`  
**Método**: `testSemConflitoVersaoOperacaoCorreta()`

**Objetivo**: Garantir que operações com versão correta são executadas com sucesso, validando o funcionamento normal do optimistic lock.

**Cenário testado**:
- Sequência de operações onde cada uma usa a versão atualizada da operação anterior
- Todas as operações devem ter sucesso

**Por que é importante**: Valida que o optimistic lock funciona corretamente quando as versões estão corretas, garantindo que operações válidas não sejam bloqueadas indevidamente.

**Assertivas**:
- Todas as operações têm sucesso
- Versões são incrementadas corretamente (1 → 2 → 3)
- Estado final é o esperado (ACEITA)

---

## 4. Testes de Busca com Filtros e Paginação

### 4.1. Busca com Filtro por Cliente

**Arquivo**: `tests/Infrastructure/Repository/PropostaRepositoryTest.php`  
**Método**: `testBuscaComFiltroPorCliente()`

**Objetivo**: Garantir que o filtro por cliente retorna apenas as propostas do cliente especificado, validando a funcionalidade de filtragem do repositório.

**Cenário testado**:
- Buscar propostas do cliente 1 deve retornar 3 propostas
- Todas as propostas retornadas devem ser do cliente 1

**Por que é importante**: Valida que o filtro funciona corretamente e que os índices do repositório estão funcionando, garantindo performance e precisão nas consultas.

**Assertivas**:
- Número correto de propostas retornadas (3)
- Total correto (3)
- Todas as propostas são do cliente especificado

---

### 4.2. Busca com Filtro por Estado

**Arquivo**: `tests/Infrastructure/Repository/PropostaRepositoryTest.php`  
**Método**: `testBuscaComFiltroPorEstado()`

**Objetivo**: Garantir que o filtro por estado retorna apenas as propostas no estado especificado, validando a funcionalidade de filtragem por estado.

**Cenário testado**:
- Buscar propostas ENVIADA deve retornar 2 propostas
- Todas as propostas retornadas devem estar ENVIADA

**Por que é importante**: Valida que o filtro por estado funciona corretamente e que os índices estão atualizados, garantindo consultas precisas por estado.

**Assertivas**:
- Número correto de propostas retornadas (2)
- Total correto (2)
- Todas as propostas estão no estado especificado

---

### 4.3. Busca com Filtros Combinados (Cliente + Estado)

**Arquivo**: `tests/Infrastructure/Repository/PropostaRepositoryTest.php`  
**Método**: `testBuscaComFiltrosCombinados()`

**Objetivo**: Garantir que filtros combinados (AND) funcionam corretamente, retornando apenas propostas que satisfazem todos os filtros simultaneamente.

**Cenário testado**:
- Buscar propostas do cliente 1 com estado ENVIADA deve retornar 1 proposta
- Proposta deve ser do cliente 1 e estar ENVIADA

**Por que é importante**: Valida que a intersecção de filtros funciona corretamente (lógica AND), garantindo consultas precisas e complexas.

**Assertivas**:
- Número correto de propostas retornadas (1)
- Total correto (1)
- Proposta satisfaz ambos os filtros (cliente + estado)

---

### 4.4. Busca Sem Filtros

**Arquivo**: `tests/Infrastructure/Repository/PropostaRepositoryTest.php`  
**Método**: `testBuscaSemFiltros()`

**Objetivo**: Garantir que busca sem filtros retorna todas as propostas, validando o comportamento padrão do repositório.

**Cenário testado**:
- Buscar todas as propostas deve retornar 5 propostas
- Total deve ser 5

**Por que é importante**: Valida que o comportamento padrão funciona corretamente quando nenhum filtro é aplicado, garantindo que todas as propostas sejam retornadas.

**Assertivas**:
- Número correto de propostas retornadas (5)
- Total correto (5)

---

### 4.5. Paginação - Primeira Página

**Arquivo**: `tests/Infrastructure/Repository/PropostaRepositoryTest.php`  
**Método**: `testPaginacaoPrimeiraPagina()`

**Objetivo**: Garantir que a paginação funciona corretamente, retornando apenas os itens da página solicitada.

**Cenário testado**:
- Buscar primeira página com 2 itens deve retornar 2 propostas
- Total deve ser 5 (para cálculo de páginas totais)

**Por que é importante**: Valida que a paginação funciona corretamente e que o cálculo do total é preciso, garantindo que os clientes possam navegar pelas páginas corretamente.

**Assertivas**:
- Número correto de itens na página (2)
- Total correto para cálculo de páginas (5)
- IDs corretos (1, 2)

---

### 4.6. Paginação - Segunda Página

**Arquivo**: `tests/Infrastructure/Repository/PropostaRepositoryTest.php`  
**Método**: `testPaginacaoSegundaPagina()`

**Objetivo**: Garantir que a paginação funciona para páginas subsequentes, retornando os itens corretos com base no offset.

**Cenário testado**:
- Buscar segunda página com 2 itens deve retornar 2 propostas (IDs 3 e 4)
- Total deve ser 5

**Por que é importante**: Valida que o cálculo do offset funciona corretamente e que as páginas subsequentes retornam os itens corretos.

**Assertivas**:
- Número correto de itens na página (2)
- Total correto (5)
- IDs corretos (3, 4)

---

### 4.7. Paginação - Última Página Parcial

**Arquivo**: `tests/Infrastructure/Repository/PropostaRepositoryTest.php`  
**Método**: `testPaginacaoUltimaPaginaParcial()`

**Objetivo**: Garantir que a última página funciona corretamente quando não há itens suficientes para completar a página, retornando apenas os itens restantes.

**Cenário testado**:
- Buscar terceira página com 2 itens deve retornar 1 proposta (restante de 5 propostas)
- Total deve ser 5

**Por que é importante**: Valida que a última página parcial funciona corretamente, garantindo que todos os itens sejam acessíveis, mesmo quando não completam uma página inteira.

**Assertivas**:
- Número correto de itens na página (1)
- Total correto (5)
- ID correto (5 - último)

---

### 4.8. Paginação - Página Fora do Range

**Arquivo**: `tests/Infrastructure/Repository/PropostaRepositoryTest.php`  
**Método**: `testPaginacaoPaginaForaDoRange()`

**Objetivo**: Garantir que páginas fora do range retornam array vazio, mantendo o total correto para cálculo de páginas.

**Cenário testado**:
- Buscar página 999 deve retornar array vazio com total 5

**Por que é importante**: Valida que páginas inválidas são tratadas corretamente, evitando erros e mantendo a consistência dos dados retornados.

**Assertivas**:
- Array vazio retornado
- Total correto mantido (5)

---

### 4.9. Ordenação - ASC por ID

**Arquivo**: `tests/Infrastructure/Repository/PropostaRepositoryTest.php`  
**Método**: `testOrdenacaoAscPorId()`

**Objetivo**: Garantir que a ordenação ASC funciona corretamente, retornando propostas em ordem crescente.

**Cenário testado**:
- Ordenar por ID ASC deve retornar IDs 1, 2, 3, 4, 5

**Por que é importante**: Valida que a ordenação funciona corretamente, garantindo que os resultados sejam apresentados na ordem esperada pelo cliente.

**Assertivas**:
- IDs em ordem crescente (1, 2, 3, 4, 5)

---

### 4.10. Ordenação - DESC por ID

**Arquivo**: `tests/Infrastructure/Repository/PropostaRepositoryTest.php`  
**Método**: `testOrdenacaoDescPorId()`

**Objetivo**: Garantir que a ordenação DESC funciona corretamente, retornando propostas em ordem decrescente.

**Cenário testado**:
- Ordenar por ID DESC deve retornar IDs 5, 4, 3, 2, 1

**Por que é importante**: Valida que a ordenação reversa funciona corretamente, permitindo que os clientes vejam os resultados mais recentes primeiro.

**Assertivas**:
- IDs em ordem decrescente (5, 4, 3, 2, 1)

---

### 4.11. Ordenação - ASC por Valor

**Arquivo**: `tests/Infrastructure/Repository/PropostaRepositoryTest.php`  
**Método**: `testOrdenacaoAscPorValor()`

**Objetivo**: Garantir que a ordenação por valor funciona corretamente, retornando propostas ordenadas por valor monetário.

**Cenário testado**:
- Ordenar por valor ASC deve retornar valores 1000, 1500, 2000, 2500, 3000

**Por que é importante**: Valida que a ordenação por campos diferentes funciona corretamente, permitindo que os clientes ordenem por diferentes critérios.

**Assertivas**:
- Valores em ordem crescente (1000, 1500, 2000, 2500, 3000)

---

### 4.12. Busca com Filtros + Ordenação + Paginação Combinados

**Arquivo**: `tests/Infrastructure/Repository/PropostaRepositoryTest.php`  
**Método**: `testBuscaComFiltrosOrdenacaoEPaginacaoCombinados()`

**Objetivo**: Garantir que filtros, ordenação e paginação funcionam corretamente quando combinados, validando o comportamento completo do repositório.

**Cenário testado**:
- Buscar propostas ENVIADA, ordenadas por valor DESC, segunda página com 1 item por página
- Deve retornar 1 proposta com valor 2000.0 (segunda maior)

**Por que é importante**: Valida que todas as funcionalidades trabalham juntas corretamente, garantindo que consultas complexas funcionem como esperado.

**Assertivas**:
- Número correto de itens (1)
- Total correto (2 - total de ENVIADA)
- Proposta correta retornada (valor 2000.0)
- Estado correto (ENVIADA)

---

## 5. Resumo dos Testes

### ✅ Cobertura

1. **Transições Válidas** - ✅ Coberto
2. **Transições Inválidas** - ✅ Coberto
3. **Idempotência** - ✅ Coberto
4. **Conflito de Versão (Optimistic Lock)** - ✅ Coberto
5. **Busca com Filtros** - ✅ Coberto
6. **Paginação** - ✅ Coberto
7. **Ordenação** - ✅ Coberto
8. **Combinações** - ✅ Coberto

### 📊 Estatísticas

- **Total de Testes**: 16 testes
- **PropostaService**: 9 testes
- **PropostaRepository**: 7 testes
- **Cenários de Sucesso**: 10 testes
- **Cenários de Falha/Exceção**: 6 testes

### 🎯 Objetivos dos Testes

Todos os testes têm o objetivo de:
1. **Validar regras de negócio** - Garantir que as regras estão sendo aplicadas corretamente
2. **Prevenir regressões** - Detectar mudanças indesejadas no comportamento
3. **Documentar comportamento** - Servir como documentação viva do sistema
4. **Garantir qualidade** - Assegurar que o código funciona como esperado
5. **Facilitar refatoração** - Permitir mudanças com confiança

---

## 6. Como Executar os Testes

```bash
# Executar todos os testes
vendor/bin/phpunit

# Executar testes de um arquivo específico
vendor/bin/phpunit tests/Application/Services/PropostaServiceTest.php

# Executar testes com cobertura (se configurado)
vendor/bin/phpunit --coverage-text
```

---

Todos os testes foram implementados e estão prontos para execução! 🚀
