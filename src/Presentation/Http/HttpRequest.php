<?php

namespace App\Presentation\Http;

/**
 * Implementação concreta de HttpRequestInterface usando $_SERVER
 * 
 * Em produção, pode ser substituída por implementação com PSR-7 ou framework.
 */
class HttpRequest implements HttpRequestInterface
{
    private array $headers = [];
    private array $body = [];
    private array $queryParams = [];

    public function __construct()
    {
        $this->headers = $this->extrairHeaders();
        $this->body = $this->extrairBody();
        $this->queryParams = $_GET ?? [];
    }

    public function getMethod(): string
    {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }

    public function getPath(): string
    {
        $path = $_SERVER['REQUEST_URI'] ?? '/';
        
        // Remove query string se existir
        if (($pos = strpos($path, '?')) !== false) {
            $path = substr($path, 0, $pos);
        }
        
        return $path;
    }

    public function getHeader(string $name): ?string
    {
        $nameLower = strtolower($name);
        
        // Busca em headers já extraídos
        foreach ($this->headers as $key => $value) {
            if (strtolower($key) === $nameLower) {
                return $value;
            }
        }

        // Fallback para $_SERVER
        $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $_SERVER[$serverKey] ?? null;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getIpOrigem(): ?string
    {
        // Tenta várias variáveis $_SERVER comuns
        return $_SERVER['HTTP_X_FORWARDED_FOR'] 
            ?? $_SERVER['HTTP_X_REAL_IP'] 
            ?? $_SERVER['REMOTE_ADDR'] 
            ?? null;
    }

    public function getBody(): array
    {
        return $this->body;
    }

    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    /**
     * Extrai headers HTTP
     * 
     * @return array<string, string>
     */
    private function extrairHeaders(): array
    {
        $headers = [];

        // Tenta getallheaders() primeiro (mais comum)
        if (function_exists('getallheaders')) {
            $allHeaders = getallheaders();
            if (is_array($allHeaders)) {
                return $allHeaders;
            }
        }

        // Fallback: extrai de $_SERVER
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $headerName = str_replace('_', '-', substr($key, 5));
                $headers[$headerName] = $value;
            }
        }

        return $headers;
    }

    /**
     * Extrai body da requisição
     * 
     * @return array
     */
    private function extrairBody(): array
    {
        $method = $this->getMethod();
        
        // Apenas métodos que podem ter body
        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            return [];
        }

        $input = file_get_contents('php://input');
        if (empty($input)) {
            return [];
        }

        $decoded = json_decode($input, true);
        return is_array($decoded) ? $decoded : [];
    }
}
