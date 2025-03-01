<?php declare(strict_types=1);

namespace Vulpes\Cloud;

use Exception;
use Psr\Http\Message\ResponseInterface;

trait CloudClientTrait
{
    /**
     * @throws Exception
     */
    protected function processResponse(ResponseInterface $response): array
    {
        $responseBody = json_decode((string)$response->getBody(), true);

        ($responseBody['message'] ?? null) &&
        throw new Exception("{$responseBody["status"]} {$responseBody["message"]}", (int)$responseBody['code']);

        ($responseBody['error'] ?? null) &&
        throw new Exception("{$responseBody['error']["status"]} {$responseBody['error']["message"]}"
            . print_r($responseBody['error']["details"] ?? '', true), (int)$responseBody['error']['code']);

        return $responseBody;
    }
}