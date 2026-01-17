<?php

namespace App\Domain\Proposta\Exceptions;

/**
 * Exceção: Versão Incorreta (Optimistic Lock)
 * 
 * Lançada quando uma operação tenta atualizar uma proposta
 * com uma versão que não corresponde à versão atual no banco de dados.
 * 
 * Isso indica que a proposta foi modificada por outro processo
 * após a leitura inicial (conflito de concorrência).
 */
class VersaoIncorretaException extends \DomainException
{
    private int $versaoAtual;
    private int $versaoEsperada;

    public function __construct(
        int $versaoAtual,
        int $versaoEsperada,
        string $message = "",
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        $this->versaoAtual = $versaoAtual;
        $this->versaoEsperada = $versaoEsperada;

        if (empty($message)) {
            $message = sprintf(
                "Proposta foi modificada por outro processo. Versão atual: %d, versão esperada: %d",
                $versaoAtual,
                $versaoEsperada
            );
        }

        parent::__construct($message, $code, $previous);
    }

    /**
     * Retorna a versão atual no banco de dados
     * 
     * @return int
     */
    public function getVersaoAtual(): int
    {
        return $this->versaoAtual;
    }

    /**
     * Retorna a versão esperada pelo cliente
     * 
     * @return int
     */
    public function getVersaoEsperada(): int
    {
        return $this->versaoEsperada;
    }
}
