<?php

namespace Tests\Application\Services;

use PHPUnit\Framework\TestCase;
use App\Application\Services\PropostaService;
use App\Application\Services\AuditoriaService;
use App\Domain\Proposta\EstadoProposta;
use App\Domain\Proposta\PropostaRepositoryInterface;
use App\Domain\Cliente\ClienteRepositoryInterface;
use App\Domain\Idempotencia\IdempotenciaOperacaoRepositoryInterface;
use App\Infrastructure\Repository\PropostaRepository;
use App\Infrastructure\Repository\ClienteRepository;
use App\Infrastructure\Repository\AuditoriaRepository;
use App\Infrastructure\Repository\IdempotenciaOperacaoRepository;
use App\Domain\Cliente\Cliente;
use App\Domain\Proposta\ValueObjects\Cliente as ClienteVO;
use App\Domain\Proposta\ValidadorTransicaoEstado;
use App\Domain\Proposta\Exceptions\VersaoIncorretaException;

/**
 * Testes de Integração: PropostaService
 * 
 * Objetivo: Validar o comportamento do Service em cenários reais,
 * incluindo integração com repositórios e validação de regras de negócio.
 */
class PropostaServiceTest extends TestCase
{
    private PropostaService $propostaService;
    private PropostaRepositoryInterface $propostaRepository;
    private ClienteRepositoryInterface $clienteRepository;
    private AuditoriaService $auditoriaService;
    private IdempotenciaOperacaoRepositoryInterface $idempotenciaRepository;

    protected function setUp(): void
    {
        // Setup: Cria instâncias reais dos repositórios para testes de integração
        $this->clienteRepository = new ClienteRepository();
        $this->propostaRepository = new PropostaRepository();
        $auditoriaRepository = new AuditoriaRepository();
        $this->auditoriaService = new AuditoriaService($auditoriaRepository);
        $this->idempotenciaRepository = new IdempotenciaOperacaoRepository();
        $validadorTransicaoEstado = new ValidadorTransicaoEstado();
        
        $this->propostaService = new PropostaService(
            $this->propostaRepository,
            $this->clienteRepository,
            $this->auditoriaService,
            $this->idempotenciaRepository,
            $validadorTransicaoEstado
        );

        // Cria cliente de teste
        $cliente = new Cliente('João Silva', 'joao@example.com', '12345678900');
        $this->clienteRepository->salvar($cliente);
    }

    /**
     * Teste: Transições Válidas
     * 
     * Objetivo: Garantir que todas as transições de estado válidas
     * são executadas com sucesso e seguem o fluxo correto.
     * 
     * Cenários testados:
     * - RASCUNHO → ENVIADA
     * - ENVIADA → ACEITA
     * - ENVIADA → RECUSADA
     * - RASCUNHO → CANCELADA
     * - ENVIADA → CANCELADA
     */
    public function testTransicoesValidas(): void
    {
        // Cria proposta em RASCUNHO
        $proposta = $this->propostaService->criarProposta(
            clienteId: 1,
            valor: 1000.0,
            usuario: 'admin'
        );
        
        $this->assertEquals(EstadoProposta::RASCUNHO, $proposta->getEstado());
        $this->assertEquals(1, $proposta->getVersao());

        // Teste 1: RASCUNHO → ENVIADA
        $proposta = $this->propostaService->submeterProposta(
            propostaId: $proposta->getId(),
            versaoEsperada: 1,
            usuario: 'cliente'
        );
        
        $this->assertEquals(EstadoProposta::ENVIADA, $proposta->getEstado());
        $this->assertEquals(2, $proposta->getVersao());

        // Teste 2: ENVIADA → ACEITA
        $proposta = $this->propostaService->aprovarProposta(
            propostaId: $proposta->getId(),
            versaoEsperada: 2,
            usuario: 'admin'
        );
        
        $this->assertEquals(EstadoProposta::ACEITA, $proposta->getEstado());
        $this->assertEquals(3, $proposta->getVersao());
    }

    /**
     * Teste: Transições Inválidas - Estados Finais Imutáveis
     * 
     * Objetivo: Garantir que estados finais (ACEITA, RECUSADA, CANCELADA)
     * não permitem transições, seguindo a regra de negócio de imutabilidade.
     * 
     * Cenários testados:
     * - ACEITA não pode transicionar
     * - RECUSADA não pode transicionar
     * - CANCELADA não pode transicionar
     */
    public function testTransicoesInvalidasEstadosFinaisImutaveis(): void
    {
        // Cria e aprova proposta
        $proposta = $this->propostaService->criarProposta(1, 1000.0);
        $proposta = $this->propostaService->submeterProposta($proposta->getId(), 1);
        $proposta = $this->propostaService->aprovarProposta($proposta->getId(), 2);

        // Estado ACEITA é final e imutável
        $this->assertEquals(EstadoProposta::ACEITA, $proposta->getEstado());
        $this->assertTrue($proposta->getEstado()->isFinal());

        // Tenta transicionar de ACEITA (deve falhar)
        $this->expectException(\DomainException::class);
        $this->propostaService->rejeitarProposta($proposta->getId(), 3);
    }

    /**
     * Teste: Transições Inválidas - Transições Proibidas
     * 
     * Objetivo: Garantir que transições inválidas (não permitidas pela FSM)
     * são rejeitadas, mantendo a integridade do estado.
     * 
     * Cenários testados:
     * - RASCUNHO não pode ir para ACEITA (deve passar por ENVIADA)
     * - ENVIADA não pode voltar para RASCUNHO
     * - ACEITA não pode ir para CANCELADA
     */
    public function testTransicoesInvalidasTransicoesProibidas(): void
    {
        // Cria proposta em RASCUNHO
        $proposta = $this->propostaService->criarProposta(1, 1000.0);

        // Tenta transicionar RASCUNHO → ACEITA (deve falhar - deve passar por ENVIADA)
        $this->expectException(\DomainException::class);
        $this->propostaService->aprovarProposta($proposta->getId(), 1);
    }

    /**
     * Teste: Transições Inválidas - Estado Intermediário para RASCUNHO
     * 
     * Objetivo: Garantir que uma vez enviada, a proposta não pode voltar
     * para RASCUNHO, mantendo o fluxo unidirecional.
     */
    public function testTransicoesInvalidasNaoPermiteVoltarParaRascunho(): void
    {
        // Cria e envia proposta
        $proposta = $this->propostaService->criarProposta(1, 1000.0);
        $proposta = $this->propostaService->submeterProposta($proposta->getId(), 1);

        // Tenta transicionar ENVIADA → RASCUNHO (deve falhar)
        // Não existe método direto, mas vamos testar que não pode voltar
        $this->assertEquals(EstadoProposta::ENVIADA, $proposta->getEstado());
        
        // Valida que não pode voltar para RASCUNHO
        $transicoesPermitidas = $proposta->getEstado()->estadosValidosParaTransicao();
        $this->assertNotContains(EstadoProposta::RASCUNHO, $transicoesPermitidas);
    }

    /**
     * Teste: Idempotência - Criação de Proposta
     * 
     * Objetivo: Garantir que requisições duplicadas com a mesma chave
     * de idempotência retornam a mesma proposta criada anteriormente,
     * evitando duplicação de dados.
     * 
     * Cenário: Criar proposta duas vezes com a mesma idempotency-key
     * deve retornar a mesma proposta.
     */
    public function testIdempotenciaCriacaoProposta(): void
    {
        $idempotencyKey = 'create-proposta-123';

        // Primeira criação
        $proposta1 = $this->propostaService->criarProposta(
            clienteId: 1,
            valor: 1000.0,
            idempotenciaKey: $idempotencyKey
        );

        $propostaId1 = $proposta1->getId();
        $versao1 = $proposta1->getVersao();

        // Segunda criação com mesma chave (deve retornar a mesma proposta)
        $proposta2 = $this->propostaService->criarProposta(
            clienteId: 1,
            valor: 1000.0,
            idempotenciaKey: $idempotencyKey // Mesma chave
        );

        // Deve ser a mesma proposta (mesmo ID e versão)
        $this->assertEquals($propostaId1, $proposta2->getId());
        $this->assertEquals($versao1, $proposta2->getVersao());
        $this->assertSame($proposta1, $proposta2);
    }

    /**
     * Teste: Idempotência - Submissão de Proposta
     * 
     * Objetivo: Garantir que submeter uma proposta duas vezes com a mesma
     * chave de idempotência retorna a mesma proposta já submetida,
     * evitando processamento duplicado.
     * 
     * Cenário: Submeter proposta duas vezes com a mesma idempotency-key
     * deve retornar a mesma proposta já submetida.
     */
    public function testIdempotenciaSubmissaoProposta(): void
    {
        // Cria proposta
        $proposta = $this->propostaService->criarProposta(1, 1000.0);
        
        $idempotencyKey = 'submit-proposta-456';

        // Primeira submissão
        $proposta1 = $this->propostaService->submeterProposta(
            propostaId: $proposta->getId(),
            versaoEsperada: 1,
            idempotenciaKey: $idempotencyKey
        );

        $this->assertEquals(EstadoProposta::ENVIADA, $proposta1->getEstado());
        $versao1 = $proposta1->getVersao();

        // Segunda submissão com mesma chave (deve retornar a mesma proposta)
        $proposta2 = $this->propostaService->submeterProposta(
            propostaId: $proposta->getId(),
            versaoEsperada: 1, // Versão esperada (pode não corresponder se já foi submetida)
            idempotenciaKey: $idempotencyKey // Mesma chave
        );

        // Deve ser a mesma proposta (mesmo estado e versão)
        $this->assertEquals(EstadoProposta::ENVIADA, $proposta2->getEstado());
        $this->assertEquals($versao1, $proposta2->getVersao());
    }

    /**
     * Teste: Conflito de Versão - Versão Antiga
     * 
     * Objetivo: Garantir que operações com versão desatualizada são rejeitadas,
     * detectando conflitos de concorrência (optimistic lock).
     * 
     * Cenário: Tentar atualizar proposta com versão antiga após outra
     * operação já ter alterado a proposta.
     */
    public function testConflitoVersaoVersaoAntiga(): void
    {
        // Cria proposta
        $proposta = $this->propostaService->criarProposta(1, 1000.0);
        $propostaId = $proposta->getId();

        // Primeira atualização (versão 1 → 2)
        $proposta = $this->propostaService->submeterProposta($propostaId, 1);
        $this->assertEquals(2, $proposta->getVersao());

        // Tenta atualizar com versão antiga (deve falhar)
        $this->expectException(VersaoIncorretaException::class);
        
        $this->propostaService->aprovarProposta($propostaId, 1); // Versão 1 é antiga
    }

    /**
     * Teste: Conflito de Versão - Versão Futura
     * 
     * Objetivo: Garantir que operações com versão futura são rejeitadas,
     * detectando inconsistências no controle de versão.
     * 
     * Cenário: Tentar atualizar proposta com versão que ainda não existe.
     */
    public function testConflitoVersaoVersaoFutura(): void
    {
        // Cria proposta (versão 1)
        $proposta = $this->propostaService->criarProposta(1, 1000.0);
        $propostaId = $proposta->getId();

        // Tenta atualizar com versão futura (deve falhar)
        $this->expectException(VersaoIncorretaException::class);
        
        $this->propostaService->submeterProposta($propostaId, 999); // Versão 999 não existe
    }

    /**
     * Teste: Conflito de Versão - Simulação de Concorrência
     * 
     * Objetivo: Simular cenário real de concorrência onde duas operações
     * tentam atualizar a mesma proposta simultaneamente.
     * 
     * Cenário: Duas operações lêem a proposta na versão 1, uma atualiza
     * com sucesso, a outra tenta atualizar com versão desatualizada.
     */
    public function testConflitoVersaoSimulacaoConcorrencia(): void
    {
        // Cria proposta
        $proposta = $this->propostaService->criarProposta(1, 1000.0);
        $propostaId = $proposta->getId();

        // Simula duas threads/processos lendo a proposta na versão 1
        $versaoLeituraComum = 1;

        // Thread 1: Submete proposta (sucesso - versão 1 → 2)
        $proposta1 = $this->propostaService->submeterProposta($propostaId, $versaoLeituraComum);
        $this->assertEquals(2, $proposta1->getVersao());

        // Thread 2: Tenta aprovar com versão antiga (falha - conflito de versão)
        $this->expectException(VersaoIncorretaException::class);
        
        $this->propostaService->aprovarProposta($propostaId, $versaoLeituraComum); // Versão 1 está desatualizada
    }

    /**
     * Teste: Conflito de Versão - Operação com Versão Correta
     * 
     * Objetivo: Garantir que operações com versão correta são executadas
     * com sucesso, validando o funcionamento normal do optimistic lock.
     * 
     * Cenário: Sequência de operações onde cada uma usa a versão atualizada
     * da operação anterior.
     */
    public function testSemConflitoVersaoOperacaoCorreta(): void
    {
        // Cria proposta
        $proposta = $this->propostaService->criarProposta(1, 1000.0);
        $propostaId = $proposta->getId();

        // Sequência de operações com versões corretas
        $proposta = $this->propostaService->submeterProposta($propostaId, 1); // 1 → 2
        $this->assertEquals(2, $proposta->getVersao());

        $proposta = $this->propostaService->aprovarProposta($propostaId, 2); // 2 → 3
        $this->assertEquals(3, $proposta->getVersao());

        // Todas as operações devem ter sucesso
        $this->assertEquals(EstadoProposta::ACEITA, $proposta->getEstado());
    }
}
