<?php
namespace App\Components;

interface IAuth
{
    public function check(): bool;
    public function generateToken(): string;
}

/**
 * Класс-заглушка для авторизации.
 * Class SimpleAuth
 * @package App\Components
 */
class SimpleAuth implements IAuth
{
    /**
     * Проверка токена на валидность.
     * @return bool
     */
    public function check(): bool {
        $headers = getallheaders();
        if (empty($headers['Authorization']) ||
            !preg_match(pattern: '/^bearer\s+[a-zA-Z0-9_-]{64}$/i', subject: $headers['Authorization'])) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Генерация токена.
     * @return string
     */
    public function generateToken(): string {
        $alphabet = array_merge(
            range(0, 9),
            range('a', 'z'),
            range('A', 'Z'),
            ['-', '_']
        );

        $token = '';
        for ($i = 0; $i < count($alphabet); $i++) {
            $token .= $alphabet[array_rand(array: $alphabet)];
        }

        return $token;
    }
}

class AuthContext
{
    private IAuth $authType;

    /**
     * Конструктор класса AuthContext.
     */
    public function __construct(IAuth $authType) {
        $this->authType = $authType;
    }

    /**
     * Запускает проверку пользовательского токена.
     * @return bool
     */
    public function runCheck(): bool {
        return $this->authType->check();
    }

    /**
     * Запуск генерации токена.
     * @return string
     */
    public function getToken(): string {
        return $this->authType->generateToken();
    }
}