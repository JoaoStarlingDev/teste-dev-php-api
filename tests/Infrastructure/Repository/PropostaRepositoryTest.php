<?php

namespace Tests\Infrastructure\Repository;

use PHPUnit\Framework\TestCase;
use App\Infrastructure\Repository\PropostaRepository;
use App\Infrastructure\Repository\ClienteRepository;
use App\Domain\Proposta\Criteria\PropostaCriteria;
use App\Domain\Proposta\EstadoProposta;
use App\Domain\Proposta\Proposta;
use App\Domain\Proposta\ValueObjects\Cliente as ClienteVO;
use App\Domain\Proposta\ValueObjects\Valor;
use App\Domain\Cliente\Cliente;

/**
 * Testes de Integração: PropostaRepository
 * 
 * Objetivo: Validar o comportamento do repositório em cenários reais,
 * incluindo busca com filtros, ordenação e paginação.
 */
class PropostaRepositoryTest extends TestCase
{
    private PropostaRepository $propostaRepository;
    private ClienteRepository $clienteRepository;

    protected function setUp(): void
    {
        // Setup: Cria instâncias reais dos repositórios
        $this->clienteRepository = new ClienteRepository();
        $this->propostaRepository = new PropostaRepository();
        
        // Cria clientes de teste
        $cliente1 = new Cliente('João Silva', 'joao@example.com', '12345678900');
        $cliente2 = new Cliente('Maria Santos', 'maria@example.com', '98765432100');
        $this->clienteRepository->salvar($cliente1);
        $this->clienteRepository->salvar($cliente2);

        // Cria propostas de teste com diferentes estados e clientes
        $this->criarPropostasDeTeste();
    }

    /**
     * Cria propostas de teste com diferentes estados e clientes
     */
    private function criarPropostasDeTeste(): void
    {
        $cliente1 = new ClienteVO('João Silva', 'joao@example.com', '12345678900');
        $cliente2 = new ClienteVO('Maria Santos', 'maria@example.com', '98765432100');

        // Cliente 1: 3 propostas (RASCUNHO, ENVIADA, ACEITA)
        $proposta1 = new Proposta(1, $cliente1, new Valor(1000.0));
        $proposta1->setId(1);
        $this->propostaRepository->salvar($proposta1); // RASCUNHO

        $proposta2 = new Proposta(1, $cliente1, new Valor(2000.0));
        $proposta2->setId(2);
        $proposta2->transicionarEstado(EstadoProposta::ENVIADA);
        $this->propostaRepository->salvar($proposta2); // ENVIADA

        $proposta3 = new Proposta(1, $cliente1, new Valor(3000.0));
        $proposta3->setId(3);
        $proposta3->transicionarEstado(EstadoProposta::ENVIADA);
        $proposta3->transicionarEstado(EstadoProposta::ACEITA);
        $this->propostaRepository->salvar($proposta3); // ACEITA

        // Cliente 2: 2 propostas (RASCUNHO, ENVIADA)
        $proposta4 = new Proposta(2, $cliente2, new Valor(1500.0));
        $proposta4->setId(4);
        $this->propostaRepository->salvar($proposta4); // RASCUNHO

        $proposta5 = new Proposta(2, $cliente2, new Valor(2500.0));
        $proposta5->setId(5);
        $proposta5->transicionarEstado(EstadoProposta::ENVIADA);
        $this->propostaRepository->salvar($proposta5); // ENVIADA
    }

    /**
     * Teste: Busca com Filtro por Cliente
     * 
     * Objetivo: Garantir que o filtro por cliente retorna apenas
     * as propostas do cliente especificado, validando a funcionalidade
     * de filtragem do repositório.
     * 
     * Cenário: Buscar propostas do cliente 1 deve retornar 3 propostas.
     */
    public function testBuscaComFiltroPorCliente(): void
    {
        $criteria = new PropostaCriteria(
            clienteId: 1,
            estado: null,
            ordenarPor: 'id',
            direcao: 'ASC',
            pagina: 1,
            porPagina: 10
        );

        [$propostas, $total] = $this->propostaRepository->buscarComCriteria($criteria);

        // Cliente 1 tem 3 propostas
        $this->assertCount(3, $propostas);
        $this->assertEquals(3, $total);

        // Todas as propostas são do cliente 1
        foreach ($propostas as $proposta) {
            $this->assertEquals(1, $proposta->getClienteId());
        }
    }

    /**
     * Teste: Busca com Filtro por Estado
     * 
     * Objetivo: Garantir que o filtro por estado retorna apenas
     * as propostas no estado especificado, validando a funcionalidade
     * de filtragem por estado.
     * 
     * Cenário: Buscar propostas ENVIADA deve retornar 2 propostas.
     */
    public function testBuscaComFiltroPorEstado(): void
    {
        $criteria = new PropostaCriteria(
            clienteId: null,
            estado: EstadoProposta::ENVIADA,
            ordenarPor: 'id',
            direcao: 'ASC',
            pagina: 1,
            porPagina: 10
        );

        [$propostas, $total] = $this->propostaRepository->buscarComCriteria($criteria);

        // Existem 2 propostas ENVIADA
        $this->assertCount(2, $propostas);
        $this->assertEquals(2, $total);

        // Todas as propostas estão ENVIADA
        foreach ($propostas as $proposta) {
            $this->assertEquals(EstadoProposta::ENVIADA, $proposta->getEstado());
        }
    }

    /**
     * Teste: Busca com Filtros Combinados (Cliente + Estado)
     * 
     * Objetivo: Garantir que filtros combinados (AND) funcionam
     * corretamente, retornando apenas propostas que satisfazem
     * todos os filtros simultaneamente.
     * 
     * Cenário: Buscar propostas do cliente 1 com estado ENVIADA
     * deve retornar 1 proposta.
     */
    public function testBuscaComFiltrosCombinados(): void
    {
        $criteria = new PropostaCriteria(
            clienteId: 1,
            estado: EstadoProposta::ENVIADA,
            ordenarPor: 'id',
            direcao: 'ASC',
            pagina: 1,
            porPagina: 10
        );

        [$propostas, $total] = $this->propostaRepository->buscarComCriteria($criteria);

        // Cliente 1 tem 1 proposta ENVIADA
        $this->assertCount(1, $propostas);
        $this->assertEquals(1, $total);

        // Proposta é do cliente 1 e está ENVIADA
        $proposta = $propostas[0];
        $this->assertEquals(1, $proposta->getClienteId());
        $this->assertEquals(EstadoProposta::ENVIADA, $proposta->getEstado());
        $this->assertEquals(2, $proposta->getId()); // ID da proposta ENVIADA do cliente 1
    }

    /**
     * Teste: Busca Sem Filtros
     * 
     * Objetivo: Garantir que busca sem filtros retorna todas as propostas,
     * validando o comportamento padrão do repositório.
     * 
     * Cenário: Buscar todas as propostas deve retornar 5 propostas.
     */
    public function testBuscaSemFiltros(): void
    {
        $criteria = new PropostaCriteria(
            clienteId: null,
            estado: null,
            ordenarPor: 'id',
            direcao: 'ASC',
            pagina: 1,
            porPagina: 10
        );

        [$propostas, $total] = $this->propostaRepository->buscarComCriteria($criteria);

        // Total de 5 propostas criadas
        $this->assertCount(5, $propostas);
        $this->assertEquals(5, $total);
    }

    /**
     * Teste: Paginação - Primeira Página
     * 
     * Objetivo: Garantir que a paginação funciona corretamente,
     * retornando apenas os itens da página solicitada.
     * 
     * Cenário: Buscar primeira página com 2 itens deve retornar 2 propostas.
     */
    public function testPaginacaoPrimeiraPagina(): void
    {
        $criteria = new PropostaCriteria(
            clienteId: null,
            estado: null,
            ordenarPor: 'id',
            direcao: 'ASC',
            pagina: 1,
            porPagina: 2
        );

        [$propostas, $total] = $this->propostaRepository->buscarComCriteria($criteria);

        // Primeira página com 2 itens
        $this->assertCount(2, $propostas);
        $this->assertEquals(5, $total); // Total geral

        // Primeiros IDs
        $this->assertEquals(1, $propostas[0]->getId());
        $this->assertEquals(2, $propostas[1]->getId());
    }

    /**
     * Teste: Paginação - Segunda Página
     * 
     * Objetivo: Garantir que a paginação funciona para páginas
     * subsequentes, retornando os itens corretos com base no offset.
     * 
     * Cenário: Buscar segunda página com 2 itens deve retornar 2 propostas
     * (IDs 3 e 4).
     */
    public function testPaginacaoSegundaPagina(): void
    {
        $criteria = new PropostaCriteria(
            clienteId: null,
            estado: null,
            ordenarPor: 'id',
            direcao: 'ASC',
            pagina: 2,
            porPagina: 2
        );

        [$propostas, $total] = $this->propostaRepository->buscarComCriteria($criteria);

        // Segunda página com 2 itens
        $this->assertCount(2, $propostas);
        $this->assertEquals(5, $total); // Total geral

        // IDs 3 e 4
        $this->assertEquals(3, $propostas[0]->getId());
        $this->assertEquals(4, $propostas[1]->getId());
    }

    /**
     * Teste: Paginação - Última Página Parcial
     * 
     * Objetivo: Garantir que a última página funciona corretamente
     * quando não há itens suficientes para completar a página,
     * retornando apenas os itens restantes.
     * 
     * Cenário: Buscar terceira página com 2 itens deve retornar 1 proposta
     * (restante de 5 propostas com 2 por página).
     */
    public function testPaginacaoUltimaPaginaParcial(): void
    {
        $criteria = new PropostaCriteria(
            clienteId: null,
            estado: null,
            ordenarPor: 'id',
            direcao: 'ASC',
            pagina: 3,
            porPagina: 2
        );

        [$propostas, $total] = $this->propostaRepository->buscarComCriteria($criteria);

        // Terceira página com 1 item restante
        $this->assertCount(1, $propostas);
        $this->assertEquals(5, $total); // Total geral

        // ID 5 (último)
        $this->assertEquals(5, $propostas[0]->getId());
    }

    /**
     * Teste: Paginação - Página Fora do Range
     * 
     * Objetivo: Garantir que páginas fora do range retornam array vazio,
     * mantendo o total correto para cálculo de páginas.
     * 
     * Cenário: Buscar página 999 deve retornar array vazio com total 5.
     */
    public function testPaginacaoPaginaForaDoRange(): void
    {
        $criteria = new PropostaCriteria(
            clienteId: null,
            estado: null,
            ordenarPor: 'id',
            direcao: 'ASC',
            pagina: 999,
            porPagina: 2
        );

        [$propostas, $total] = $this->propostaRepository->buscarComCriteria($criteria);

        // Página fora do range retorna vazio
        $this->assertCount(0, $propostas);
        $this->assertEquals(5, $total); // Total geral ainda correto
    }

    /**
     * Teste: Ordenação - ASC por ID
     * 
     * Objetivo: Garantir que a ordenação ASC funciona corretamente,
     * retornando propostas em ordem crescente.
     * 
     * Cenário: Ordenar por ID ASC deve retornar IDs 1, 2, 3, 4, 5.
     */
    public function testOrdenacaoAscPorId(): void
    {
        $criteria = new PropostaCriteria(
            clienteId: null,
            estado: null,
            ordenarPor: 'id',
            direcao: 'ASC',
            pagina: 1,
            porPagina: 10
        );

        [$propostas, $total] = $this->propostaRepository->buscarComCriteria($criteria);

        // Verifica ordem crescente
        $ids = array_map(fn($p) => $p->getId(), $propostas);
        $this->assertEquals([1, 2, 3, 4, 5], $ids);
    }

    /**
     * Teste: Ordenação - DESC por ID
     * 
     * Objetivo: Garantir que a ordenação DESC funciona corretamente,
     * retornando propostas em ordem decrescente.
     * 
     * Cenário: Ordenar por ID DESC deve retornar IDs 5, 4, 3, 2, 1.
     */
    public function testOrdenacaoDescPorId(): void
    {
        $criteria = new PropostaCriteria(
            clienteId: null,
            estado: null,
            ordenarPor: 'id',
            direcao: 'DESC',
            pagina: 1,
            porPagina: 10
        );

        [$propostas, $total] = $this->propostaRepository->buscarComCriteria($criteria);

        // Verifica ordem decrescente
        $ids = array_map(fn($p) => $p->getId(), $propostas);
        $this->assertEquals([5, 4, 3, 2, 1], $ids);
    }

    /**
     * Teste: Ordenação - ASC por Valor
     * 
     * Objetivo: Garantir que a ordenação por valor funciona corretamente,
     * retornando propostas ordenadas por valor monetário.
     * 
     * Cenário: Ordenar por valor ASC deve retornar valores 1000, 1500, 2000, 2500, 3000.
     */
    public function testOrdenacaoAscPorValor(): void
    {
        $criteria = new PropostaCriteria(
            clienteId: null,
            estado: null,
            ordenarPor: 'valor',
            direcao: 'ASC',
            pagina: 1,
            porPagina: 10
        );

        [$propostas, $total] = $this->propostaRepository->buscarComCriteria($criteria);

        // Verifica ordem crescente por valor
        $valores = array_map(fn($p) => $p->getValor()->getValor(), $propostas);
        $this->assertEquals([1000.0, 1500.0, 2000.0, 2500.0, 3000.0], $valores);
    }

    /**
     * Teste: Busca com Filtros + Ordenação + Paginação Combinados
     * 
     * Objetivo: Garantir que filtros, ordenação e paginação funcionam
     * corretamente quando combinados, validando o comportamento completo
     * do repositório.
     * 
     * Cenário: Buscar propostas ENVIADA, ordenadas por valor DESC,
     * segunda página com 1 item por página deve retornar 1 proposta.
     */
    public function testBuscaComFiltrosOrdenacaoEPaginacaoCombinados(): void
    {
        $criteria = new PropostaCriteria(
            clienteId: null,
            estado: EstadoProposta::ENVIADA,
            ordenarPor: 'valor',
            direcao: 'DESC',
            pagina: 2,
            porPagina: 1
        );

        [$propostas, $total] = $this->propostaRepository->buscarComCriteria($criteria);

        // 2 propostas ENVIADA, segunda página com 1 item
        $this->assertCount(1, $propostas);
        $this->assertEquals(2, $total); // Total de propostas ENVIADA

        // Segunda proposta ENVIADA por valor DESC (2000.0 - segunda maior)
        $proposta = $propostas[0];
        $this->assertEquals(EstadoProposta::ENVIADA, $proposta->getEstado());
        $this->assertEquals(2000.0, $proposta->getValor()->getValor());
    }

}
