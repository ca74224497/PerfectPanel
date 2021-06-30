<?php
namespace App\Components;

use JetBrains\PhpStorm\NoReturn;

/**
 * Class Response
 * @package App\Components
 */
class Response
{
    /**
     * Отправка JSON-ответа пользователю.
     * @param string|array $payload
     * @param string $type
     * @param int $httpCode
     * @return void
     */
    #[NoReturn]
    public static function send(string|array $payload, string $type = 'success', int $httpCode = HttpCodes::HTTP_OK): void {
        http_response_code(response_code: $httpCode);

        $response = [
            'status' => $type,
            'code' => $httpCode
        ];

        $type === 'success' ?
            $response['data'] = $payload : $response['message'] = $payload;

        die(json_encode(value: $response));
    }
}