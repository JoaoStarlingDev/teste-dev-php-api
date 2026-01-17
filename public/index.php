<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Infrastructure\Repository\PropostaRepository;
use App\Infrastructure\Repository\AuditoriaRepository;
use App\Infrastructure\Repository\ClienteRepository;
use App\Infrastructure\Repository\IdempotenciaOperacaoRepository;
use App\Application\Services\ClienteService;
use App\Application\Services\PropostaService;
use App\Application\Services\AuditoriaService;
use App\Domain\Proposta\ValidadorTransicaoEstado;
use App\Presentation\Controllers\Api\V1\ClienteController;
use App\Presentation\Controllers\Api\V1\PropostaController;
use App\Presentation\Controllers\AuditoriaController;
use App\Application\UseCases\BuscarAuditoriaUseCase;
use App\Presentation\Http\HttpRequest;
use App\Presentation\Router;

// Container simples (DI manual)

// HTTP Request (abstração)
$httpRequest = new HttpRequest();

// Repositories
$auditoriaRepository = new AuditoriaRepository();
$clienteRepository = new ClienteRepository();
$idempotenciaRepository = new IdempotenciaOperacaoRepository();
$propostaRepository = new PropostaRepository(); // Removida dependência de ClienteRepository

// Domain Services
$validadorTransicaoEstado = new ValidadorTransicaoEstado();

// Application Services
$auditoriaService = new AuditoriaService($auditoriaRepository);
$clienteService = new ClienteService($clienteRepository);
$propostaService = new PropostaService(
    $propostaRepository,
    $clienteRepository,
    $auditoriaService,
    $idempotenciaRepository,
    $validadorTransicaoEstado
);

// Use Cases
$buscarAuditoriaUseCase = new BuscarAuditoriaUseCase($auditoriaRepository);

// Controllers (injetam HttpRequest)
$clienteController = new ClienteController($clienteService, $httpRequest);
$propostaController = new PropostaController($propostaService, $httpRequest);
$auditoriaController = new AuditoriaController($buscarAuditoriaUseCase);

// Router
$router = new Router($clienteController, $propostaController, $auditoriaController);

// Processa requisição usando HttpRequest
$method = $httpRequest->getMethod();
$path = $httpRequest->getPath();
$dados = array_merge($httpRequest->getBody(), $httpRequest->getQueryParams());

// Processa rota (retorna [resposta, statusCode])
[$resposta, $statusCode] = $router->processar($method, $path, $dados);

// Define headers
header('Content-Type: application/json; charset=utf-8');
header('X-API-Version: v1');

// Define status code
http_response_code($statusCode);

// Retorna JSON
echo json_encode($resposta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
