<?php

namespace App\Request;

use ErrorException;
use App\Utils\HttpCodes;

/**
 * Class RemoteQuery
 * @package App\Request
 */
class RemoteQuery
{
    /**
     * Получение JSON-данных с удаленного URL.
     * @param string $url
     * @return array
     * @throws ErrorException
     */
    public static function getJson(string $url = ''): array
    {
        $result = file_get_contents(filename: $url);

        if (!is_string($result)) {
            throw new ErrorException(
                message: 'Unable to get data from API server',
                code: HttpCodes::HTTP_BAD_REQUEST
            );
        }

        $data = json_decode(json: $result, associative: true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ErrorException(
                message: 'Incorrectly formed JSON',
                code: HttpCodes::HTTP_BAD_REQUEST
            );
        }

        return $data;
    }
}
