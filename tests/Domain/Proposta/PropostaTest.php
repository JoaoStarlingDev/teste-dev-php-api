<?php

namespace Tests\Domain\Proposta;

use PHPUnit\Framework\TestCase;
use App\Domain\Proposta\Proposta;
use App\Domain\Proposta\EstadoProposta;
use App\Domain\Proposta\ValueObjects\Cliente;
use App\Domain\Proposta\ValueObjects\Valor;
use App\Domain\Proposta\ValueObjects\IdempotenciaKey;

class PropostaTest extends TestCase
{
    public function testCriarPropostaComEstadoRascunho(): void
    {
        $clienteId = 1;
        $cliente = new Cliente('João Silva', 'joao@example.com');
        $valor = new Valor(1000.00);
        $proposta = new Proposta($clienteId, $cliente, $valor);

        $this->assertNull($proposta->getId());
        $this->assertEquals(EstadoProposta::RASCUNHO, $proposta->getEstado());
        $this->assertEquals(1, $proposta->getVersao());
    }

    public function testTransicionarDeRascunhoParaEnviada(): void
    {
        $clienteId = 1;
        $cliente = new Cliente('João Silva', 'joao@example.com');
        $valor = new Valor(1000.00);
        $proposta = new Proposta($clienteId, $cliente, $valor);

        $proposta->transicionarEstado(EstadoProposta::ENVIADA);

        $this->assertEquals(EstadoProposta::ENVIADA, $proposta->getEstado());
        $this->assertEquals(2, $proposta->getVersao());
    }

    public function testNaoPermiteTransicaoInvalida(): void
    {
        $clienteId = 1;
        $cliente = new Cliente('João Silva', 'joao@example.com');
        $valor = new Valor(1000.00);
        $proposta = new Proposta($clienteId, $cliente, $valor);

        $proposta->transicionarEstado(EstadoProposta::ENVIADA);
        $proposta->transicionarEstado(EstadoProposta::ACEITA);

        $this->expectException(\DomainException::class);
        $proposta->transicionarEstado(EstadoProposta::RASCUNHO);
    }

    public function testNaoPermiteEdicaoAposEnviada(): void
    {
        $clienteId = 1;
        $cliente = new Cliente('João Silva', 'joao@example.com');
        $valor = new Valor(1000.00);
        $proposta = new Proposta($clienteId, $cliente, $valor);

        $proposta->transicionarEstado(EstadoProposta::ENVIADA);

        $this->expectException(\DomainException::class);
        $proposta->atualizarValor(new Valor(2000.00));
    }

    public function testOptimisticLock(): void
    {
        $clienteId = 1;
        $cliente = new Cliente('João Silva', 'joao@example.com');
        $valor = new Valor(1000.00);
        $proposta = new Proposta($clienteId, $cliente, $valor);

        $this->assertTrue($proposta->verificarVersao(1));
        $this->assertFalse($proposta->verificarVersao(2));

        $proposta->transicionarEstado(EstadoProposta::ENVIADA);

        $this->assertTrue($proposta->verificarVersao(2));
        $this->assertFalse($proposta->verificarVersao(1));
    }
}
