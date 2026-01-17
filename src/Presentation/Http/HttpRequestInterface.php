<?php

namespace App\Presentation\Http;

/**
 * Interface para abstração de Requisição HTTP
 * 
 * Remove acoplamento direto com $_SERVER e getallheaders().
 */
interface HttpRequestInterface
{
    /**
     * Retorna o método HTTP (GET, POST, etc)
     * 
     * @return string
     */
    public function getMethod(): string;

    /**
     * Retorna o caminho da requisição
     * 
     * @return string
     */
    public function getPath(): string;

    /**
     * Retorna um header HTTP
     * 
     * @param string $name Nome do header (case-insensitive)
     * @return string|null
     */
    public function getHeader(string $name): ?string;

    /**
     * Retorna todos os headers HTTP
     * 
     * @return array<string, string>
     */
    public function getHeaders(): array;

    /**
     * Retorna IP de origem da requisição
     * 
     * @return string|null
     */
    public function getIpOrigem(): ?string;

    /**
     * Retorna o body da requisição como array
     * 
     * @return array
     */
    public function getBody(): array;

    /**
     * Retorna query parameters como array
     * 
     * @return array
     */
    public function getQueryParams(): array;
}
