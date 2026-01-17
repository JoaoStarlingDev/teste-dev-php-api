<?php

namespace App\Application\Services;

use App\Domain\Proposta\Proposta;
use App\Domain\Proposta\EstadoProposta;
use App\Domain\Proposta\PropostaRepositoryInterface;
use App\Domain\Proposta\ValidadorTransicaoEstado;
use App\Domain\Proposta\Criteria\PropostaCriteria;
use App\Domain\Proposta\ValueObjects\Cliente as ClienteVO;
use App\Domain\Proposta\ValueObjects\Valor;
use App\Domain\Proposta\ValueObjects\IdempotenciaKey;
use App\Domain\Proposta\Exceptions\EstadoFinalImutavelException;
use App\Domain\Proposta\Exceptions\TransicaoEstadoInvalidaException;
use App\Domain\Proposta\Exceptions\PropostaNaoPodeSerEditadaException;
use App\Domain\Proposta\Exceptions\VersaoIncorretaException;
use App\Domain\Cliente\ClienteRepositoryInterface;
use App\Domain\Auditoria\EventoAuditoria;
use App\Domain\Idempotencia\IdempotenciaOperacaoRepositoryInterface;
use App\Domain\Idempotencia\IdempotenciaOperacao;
use DateTimeImmutable;

/**
 * Service: Proposta
 * 
 * Responsável por operações relacionadas a propostas, incluindo:
 * - Criação de propostas
 * - Transições de estado
 * - Controle de versão (optimistic lock)
 * - Registro de auditoria
 * - Validação de regras de negócio
 */
class PropostaService
{
    public function __construct(
        private PropostaRepositoryInterface $propostaRepository,
        private ClienteRepositoryInterface $clienteRepository,
        private AuditoriaService $auditoriaService,
        private IdempotenciaOperacaoRepositoryInterface $idempotenciaRepository,
        private ValidadorTransicaoEstado $validador
    ) {
    }

    /**
     * Cria uma nova proposta em estado RASCUNHO (DRAFT)
     * 
     * @param int $clienteId ID do cliente
     * @param float $valor Valor da proposta
     * @param string|null $idempotenciaKey Chave de idempotência (opcional)
     * @param string|null $usuario Usuário que está criando
     * @param string|null $ipOrigem IP de origem da requisição
     * @return Proposta
     * @throws \DomainException Se cliente não existe ou dados inválidos
     */
    public function criarProposta(
        int $clienteId,
        float $valor,
        ?string $idempotenciaKey = null,
        ?string $usuario = null,
        ?string $ipOrigem = null
    ): Proposta {
        // Verifica idempotência
        if ($idempotenciaKey !== null) {
            $key = new IdempotenciaKey($idempotenciaKey);
            $propostaExistente = $this->propostaRepository->buscarPorIdempotenciaKey($key);
            
            if ($propostaExistente !== null) {
                // Retorna proposta existente (idempotência)
                return $propostaExistente;
            }
        }

        // Busca cliente
        $cliente = $this->clienteRepository->buscarPorId($clienteId);
        if ($cliente === null) {
            throw new \DomainException("Cliente com ID {$clienteId} não encontrado");
        }

        // Cria Value Objects
        $clienteVO = new ClienteVO(
            $cliente->getNome(),
            $cliente->getEmail(),
            $cliente->getDocumento()
        );
        $valorObj = new Valor($valor);
        $keyObj = $idempotenciaKey ? new IdempotenciaKey($idempotenciaKey) : null;

        // Cria proposta (sempre inicia em RASCUNHO)
        $proposta = new Proposta($clienteId, $clienteVO, $valorObj, $keyObj);
        $this->propostaRepository->salvar($proposta);

        // Registra auditoria automática: CREATED
        // Serialização será feita na camada de Infrastructure
        $this->auditoriaService->registrarCriacao(
            'Proposta',
            $proposta->getId(),
            $this->serializarParaAuditoria($proposta),
            $usuario,
            $ipOrigem
        );

        return $proposta;
    }

    /**
     * Submete uma proposta (RASCUNHO → ENVIADA)
     * 
     * Suporta idempotência via idempotenciaKey.
     * Se a mesma chave for usada novamente, retorna a proposta já submetida.
     * 
     * @param int $propostaId ID da proposta
     * @param int $versaoEsperada Versão esperada (optimistic lock)
     * @param string|null $idempotenciaKey Chave de idempotência (opcional)
     * @param string|null $usuario Usuário que está submetendo
     * @param string|null $ipOrigem IP de origem da requisição
     * @return Proposta
     * @throws VersaoIncorretaException Se versão incorreta
     * @throws TransicaoEstadoInvalidaException Se transição inválida
     * @throws \DomainException Se proposta não existe
     */
    public function submeterProposta(
        int $propostaId,
        int $versaoEsperada,
        ?string $idempotenciaKey = null,
        ?string $usuario = null,
        ?string $ipOrigem = null
    ): Proposta {
        // Verifica idempotência primeiro
        if ($idempotenciaKey !== null) {
            $operacaoExistente = $this->idempotenciaRepository->buscarPorKeyETipo(
                $idempotenciaKey,
                'submeter_proposta'
            );

            if ($operacaoExistente !== null) {
                // Busca proposta já submetida e retorna (idempotência)
                $propostaExistente = $this->propostaRepository->buscarPorId($operacaoExistente->getEntidadeId());
                if ($propostaExistente !== null) {
                    return $propostaExistente;
                }
            }
        }

        $proposta = $this->buscarEValidarVersao($propostaId, $versaoEsperada);
        
        $estadoAnterior = $proposta->getEstado();
        $dadosAnteriores = $this->serializarParaAuditoria($proposta);

        // Valida e executa transição
        $this->validador->validarTransicao($proposta->getEstado(), EstadoProposta::ENVIADA);
        $proposta->transicionarEstado(EstadoProposta::ENVIADA, $this->validador);

        // Salva proposta
        $this->propostaRepository->salvar($proposta);

        // Registra idempotência se chave fornecida
        if ($idempotenciaKey !== null) {
            $idempotenciaOperacao = new IdempotenciaOperacao(
                $idempotenciaKey,
                'submeter_proposta',
                $proposta->getId(),
                $this->serializarParaAuditoria($proposta)
            );
            $this->idempotenciaRepository->salvar($idempotenciaOperacao);
        }

        // Registra auditoria automática: STATUS_CHANGED
        $this->auditoriaService->registrarMudancaEstado(
            'Proposta',
            $proposta->getId(),
            $estadoAnterior->value,
            $proposta->getEstado()->value,
            $dadosAnteriores,
            $this->serializarParaAuditoria($proposta),
            $usuario,
            $ipOrigem
        );

        return $proposta;
    }

    /**
     * Aprova uma proposta (ENVIADA → ACEITA)
     * 
     * @param int $propostaId ID da proposta
     * @param int $versaoEsperada Versão esperada (optimistic lock)
     * @param string|null $usuario Usuário que está aprovando
     * @param string|null $ipOrigem IP de origem da requisição
     * @return Proposta
     * @throws VersaoIncorretaException Se versão incorreta
     * @throws TransicaoEstadoInvalidaException Se transição inválida
     * @throws \DomainException Se proposta não existe
     */
    public function aprovarProposta(
        int $propostaId,
        int $versaoEsperada,
        ?string $usuario = null,
        ?string $ipOrigem = null
    ): Proposta {
        $proposta = $this->buscarEValidarVersao($propostaId, $versaoEsperada);
        
        $estadoAnterior = $proposta->getEstado();
        $dadosAnteriores = $this->serializarParaAuditoria($proposta);

        // Valida e executa transição
        $this->validador->validarTransicao($proposta->getEstado(), EstadoProposta::ACEITA);
        $proposta->transicionarEstado(EstadoProposta::ACEITA, $this->validador);

        // Salva proposta
        $this->propostaRepository->salvar($proposta);

        // Registra auditoria automática: STATUS_CHANGED
        $this->auditoriaService->registrarMudancaEstado(
            'Proposta',
            $proposta->getId(),
            $estadoAnterior->value,
            $proposta->getEstado()->value,
            $dadosAnteriores,
            $this->serializarParaAuditoria($proposta),
            $usuario,
            $ipOrigem
        );

        return $proposta;
    }

    /**
     * Rejeita uma proposta (ENVIADA → RECUSADA)
     * 
     * @param int $propostaId ID da proposta
     * @param int $versaoEsperada Versão esperada (optimistic lock)
     * @param string|null $usuario Usuário que está rejeitando
     * @param string|null $ipOrigem IP de origem da requisição
     * @return Proposta
     * @throws VersaoIncorretaException Se versão incorreta
     * @throws TransicaoEstadoInvalidaException Se transição inválida
     * @throws \DomainException Se proposta não existe
     */
    public function rejeitarProposta(
        int $propostaId,
        int $versaoEsperada,
        ?string $usuario = null,
        ?string $ipOrigem = null
    ): Proposta {
        $proposta = $this->buscarEValidarVersao($propostaId, $versaoEsperada);
        
        $estadoAnterior = $proposta->getEstado();
        $dadosAnteriores = $this->serializarParaAuditoria($proposta);

        // Valida e executa transição
        $this->validador->validarTransicao($proposta->getEstado(), EstadoProposta::RECUSADA);
        $proposta->transicionarEstado(EstadoProposta::RECUSADA, $this->validador);

        // Salva proposta
        $this->propostaRepository->salvar($proposta);

        // Registra auditoria automática: STATUS_CHANGED
        $this->auditoriaService->registrarMudancaEstado(
            'Proposta',
            $proposta->getId(),
            $estadoAnterior->value,
            $proposta->getEstado()->value,
            $dadosAnteriores,
            $this->serializarParaAuditoria($proposta),
            $usuario,
            $ipOrigem
        );

        return $proposta;
    }

    /**
     * Cancela uma proposta (RASCUNHO/ENVIADA → CANCELADA)
     * 
     * @param int $propostaId ID da proposta
     * @param int $versaoEsperada Versão esperada (optimistic lock)
     * @param string|null $usuario Usuário que está cancelando
     * @param string|null $ipOrigem IP de origem da requisição
     * @return Proposta
     * @throws VersaoIncorretaException Se versão incorreta
     * @throws TransicaoEstadoInvalidaException Se transição inválida
     * @throws \DomainException Se proposta não existe
     */
    public function cancelarProposta(
        int $propostaId,
        int $versaoEsperada,
        ?string $usuario = null,
        ?string $ipOrigem = null
    ): Proposta {
        $proposta = $this->buscarEValidarVersao($propostaId, $versaoEsperada);
        
        $estadoAnterior = $proposta->getEstado();
        $dadosAnteriores = $this->serializarParaAuditoria($proposta);

        // Valida permissão de cancelamento
        $this->validador->validarPermissaoCancelamento($proposta->getEstado());

        // Valida e executa transição
        $this->validador->validarTransicao($proposta->getEstado(), EstadoProposta::CANCELADA);
        $proposta->transicionarEstado(EstadoProposta::CANCELADA, $this->validador);

        // Salva proposta
        $this->propostaRepository->salvar($proposta);

        // Registra auditoria automática: STATUS_CHANGED
        $this->auditoriaService->registrarMudancaEstado(
            'Proposta',
            $proposta->getId(),
            $estadoAnterior->value,
            $proposta->getEstado()->value,
            $dadosAnteriores,
            $this->serializarParaAuditoria($proposta),
            $usuario,
            $ipOrigem
        );

        return $proposta;
    }

    /**
     * Atualiza campos de uma proposta (exceto estado)
     * 
     * Apenas propostas em RASCUNHO podem ser editadas.
     * Registra automaticamente evento UPDATED_FIELDS.
     * 
     * @param int $propostaId ID da proposta
     * @param int $versaoEsperada Versão esperada (optimistic lock)
     * @param float|null $valor Novo valor (opcional)
     * @param string|null $usuario Usuário que está atualizando
     * @param string|null $ipOrigem IP de origem da requisição
     * @return Proposta
     * @throws VersaoIncorretaException Se versão incorreta
     * @throws PropostaNaoPodeSerEditadaException Se não pode ser editada
     * @throws \DomainException Se proposta não existe
     */
    public function atualizarProposta(
        int $propostaId,
        int $versaoEsperada,
        ?float $valor = null,
        ?string $usuario = null,
        ?string $ipOrigem = null
    ): Proposta {
        $proposta = $this->buscarEValidarVersao($propostaId, $versaoEsperada);
        
        $dadosAnteriores = $this->serializarParaAuditoria($proposta);
        $houveAlteracao = false;

        // Atualiza valor se fornecido
        if ($valor !== null) {
            $novoValor = new Valor($valor);
            $proposta->atualizarValor($novoValor, $this->validador);
            $houveAlteracao = true;
        }

        // Se não houve alteração, apenas retorna (não registra auditoria)
        if (!$houveAlteracao) {
            return $proposta;
        }

        // Salva proposta
        $this->propostaRepository->salvar($proposta);

        // Registra auditoria automática: UPDATED_FIELDS
        $this->auditoriaService->registrarAtualizacao(
            'Proposta',
            $proposta->getId(),
            $dadosAnteriores,
            $this->serializarParaAuditoria($proposta),
            $usuario,
            $ipOrigem
        );

        return $proposta;
    }

    /**
     * Busca proposta por ID
     * 
     * @param int $id
     * @return Proposta|null
     */
    public function buscarPorId(int $id): ?Proposta
    {
        return $this->propostaRepository->buscarPorId($id);
    }

    /**
     * Lista propostas com filtros, ordenação e paginação
     * 
     * @param PropostaCriteria $criteria Critérios de busca
     * @return array [Proposta[], total]
     */
    public function listarComCriteria(PropostaCriteria $criteria): array
    {
        return $this->propostaRepository->buscarComCriteria($criteria);
    }

    /**
     * Busca proposta e valida versão (optimistic lock)
     * 
     * @param int $propostaId
     * @param int $versaoEsperada
     * @return Proposta
     * @throws VersaoIncorretaException Se versão incorreta
     * @throws \DomainException Se proposta não existe
     */
    private function buscarEValidarVersao(int $propostaId, int $versaoEsperada): Proposta
    {
        $proposta = $this->propostaRepository->buscarPorId($propostaId);

        if ($proposta === null) {
            throw new \DomainException("Proposta com ID {$propostaId} não encontrada");
        }

        // Optimistic lock: verifica versão
        if (!$proposta->verificarVersao($versaoEsperada)) {
            throw new VersaoIncorretaException(
                $proposta->getVersao(),
                $versaoEsperada
            );
        }

        return $proposta;
    }

    /**
     * Serializa proposta para auditoria (dados mínimos necessários)
     * 
     * Esta serialização é apenas para auditoria interna.
     * Serialização para resposta HTTP deve ser feita na Presentation.
     * 
     * @param Proposta $proposta
     * @return array
     */
    private function serializarParaAuditoria(Proposta $proposta): array
    {
        return [
            'id' => $proposta->getId(),
            'cliente_id' => $proposta->getClienteId(),
            'cliente' => [
                'nome' => $proposta->getCliente()->getNome(),
                'email' => $proposta->getCliente()->getEmail(),
                'documento' => $proposta->getCliente()->getDocumento(),
            ],
            'valor' => $proposta->getValor()->getValor(),
            'estado' => $proposta->getEstado()->value,
            'versao' => $proposta->getVersao(),
            'idempotencia_key' => $proposta->getIdempotenciaKey()?->getKey(),
        ];
    }
}
