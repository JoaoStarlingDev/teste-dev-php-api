<?php

namespace App\Presentation\Http;

/**
 * Formatador de Respostas HTTP
 * 
 * Padroniza todas as respostas da API.
 */
class ResponseFormatter
{
    /**
     * Formata resposta de sucesso
     * 
     * @param mixed $data
     * @param int $statusCode
     * @param string|null $message
     * @return array
     */
    public static function success($data, int $statusCode = 200, ?string $message = null): array
    {
        $response = [
            'success' => true,
            'data' => $data,
        ];

        if ($message !== null) {
            $response['message'] = $message;
        }

        return $response;
    }

    /**
     * Formata resposta de erro
     * 
     * @param string $message
     * @param int $statusCode
     * @param array|null $errors
     * @return array
     */
    public static function error(string $message, int $statusCode = 400, ?array $errors = null): array
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return $response;
    }

    /**
     * Formata resposta de erro de validação
     * 
     * @param array $errors
     * @return array
     */
    public static function validationError(array $errors): array
    {
        return self::error('Erro de validação', 422, $errors);
    }

    /**
     * Formata resposta de recurso não encontrado
     * 
     * @param string $resource
     * @return array
     */
    public static function notFound(string $resource = 'Recurso'): array
    {
        return self::error("{$resource} não encontrado", 404);
    }

    /**
     * Formata resposta de conflito (optimistic lock, etc)
     * 
     * @param string $message
     * @return array
     */
    public static function conflict(string $message): array
    {
        return self::error($message, 409);
    }

    /**
     * Formata resposta paginada
     * 
     * @param array $data
     * @param int $page
     * @param int $perPage
     * @param int|null $total
     * @return array
     */
    public static function paginated(array $data, int $page, int $perPage, ?int $total = null): array
    {
        $response = [
            'success' => true,
            'data' => $data,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
            ],
        ];

        if ($total !== null) {
            $response['pagination']['total'] = $total;
            $response['pagination']['total_pages'] = (int) ceil($total / $perPage);
        }

        return $response;
    }
}
