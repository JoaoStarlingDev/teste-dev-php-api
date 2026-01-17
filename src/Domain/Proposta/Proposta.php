<?php

namespace App\Domain\Proposta;

use App\Domain\Proposta\ValueObjects\Valor;
use App\Domain\Proposta\ValueObjects\Cliente;
use App\Domain\Proposta\ValueObjects\IdempotenciaKey;
use App\Domain\Proposta\Exceptions\EstadoFinalImutavelException;
use App\Domain\Proposta\Exceptions\TransicaoEstadoInvalidaException;
use App\Domain\Proposta\Exceptions\PropostaNaoPodeSerEditadaException;
use DateTimeImmutable;

/**
 * Entidade de Domínio: Proposta
 * 
 * Representa uma proposta no sistema com controle de estado,
 * versionamento para optimistic lock e auditoria.
 */
class Proposta
{
    private ?int $id = null;
    private int $clienteId; // ID do cliente diretamente
    private Cliente $cliente; // Value Object para dados do cliente
    private Valor $valor;
    private EstadoProposta $estado;
    private int $versao = 1;
    private DateTimeImmutable $criadoEm;
    private ?DateTimeImmutable $atualizadoEm = null;
    private ?IdempotenciaKey $idempotenciaKey = null;

    public function __construct(
        int $clienteId,
        Cliente $cliente,
        Valor $valor,
        ?IdempotenciaKey $idempotenciaKey = null
    ) {
        $this->clienteId = $clienteId;
        $this->cliente = $cliente;
        $this->valor = $valor;
        $this->estado = EstadoProposta::RASCUNHO;
        $this->criadoEm = new DateTimeImmutable();
        $this->idempotenciaKey = $idempotenciaKey;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getClienteId(): int
    {
        return $this->clienteId;
    }

    public function getCliente(): Cliente
    {
        return $this->cliente;
    }

    public function getValor(): Valor
    {
        return $this->valor;
    }

    public function getEstado(): EstadoProposta
    {
        return $this->estado;
    }

    public function getVersao(): int
    {
        return $this->versao;
    }

    public function getCriadoEm(): DateTimeImmutable
    {
        return $this->criadoEm;
    }

    public function getAtualizadoEm(): ?DateTimeImmutable
    {
        return $this->atualizadoEm;
    }

    public function getIdempotenciaKey(): ?IdempotenciaKey
    {
        return $this->idempotenciaKey;
    }

    /**
     * Transiciona o estado da proposta seguindo as regras de negócio
     * 
     * Utiliza o ValidadorTransicaoEstado para garantir segurança de estado.
     * Estados finais são imutáveis e não permitem transições.
     * 
     * @param EstadoProposta $novoEstado
     * @param ValidadorTransicaoEstado|null $validador Se não fornecido, cria novo
     * @throws EstadoFinalImutavelException Se estado atual é final
     * @throws TransicaoEstadoInvalidaException Se transição não é válida
     */
    public function transicionarEstado(EstadoProposta $novoEstado, ?ValidadorTransicaoEstado $validador = null): void
    {
        $validador = $validador ?? new ValidadorTransicaoEstado();
        
        // Valida a transição (lança exceções específicas se inválida)
        $validador->validarTransicao($this->estado, $novoEstado);

        // Transição válida - atualiza estado
        $this->estado = $novoEstado;
        $this->incrementarVersao();
    }

    /**
     * Atualiza o valor da proposta
     * 
     * Apenas propostas em RASCUNHO podem ser editadas.
     * Estados finais são imutáveis.
     * 
     * @param Valor $novoValor
     * @param ValidadorTransicaoEstado|null $validador Se não fornecido, cria novo
     * @throws PropostaNaoPodeSerEditadaException Se estado não permite edição
     * @throws EstadoFinalImutavelException Se estado é final
     */
    public function atualizarValor(Valor $novoValor, ?ValidadorTransicaoEstado $validador = null): void
    {
        $validador = $validador ?? new ValidadorTransicaoEstado();
        
        // Valida se pode editar (lança exceção se não permitido)
        $validador->validarPermissaoEdicao($this->estado);

        // Edição permitida - atualiza valor
        $this->valor = $novoValor;
        $this->incrementarVersao();
    }

    /**
     * Incrementa a versão para controle de concorrência (optimistic lock)
     */
    private function incrementarVersao(): void
    {
        $this->versao++;
        $this->atualizadoEm = new DateTimeImmutable();
    }

    /**
     * Verifica se a versão atual corresponde à esperada (optimistic lock)
     */
    public function verificarVersao(int $versaoEsperada): bool
    {
        return $this->versao === $versaoEsperada;
    }

    /**
     * Verifica se a proposta está em estado final (imutável)
     * 
     * @return bool
     */
    public function isEstadoFinal(): bool
    {
        return $this->estado->isFinal();
    }

    /**
     * Verifica se a proposta pode ser editada
     * 
     * @return bool
     */
    public function podeSerEditada(): bool
    {
        return $this->estado->permiteEdicao();
    }
}
