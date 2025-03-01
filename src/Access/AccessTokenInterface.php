<?php declare(strict_types=1);

namespace Vulpes\Cloud\Access;

use Throwable;

interface AccessTokenInterface
{
    /**
     * @throws Throwable
     */
    public function getToken(): string;

    /**
     * @throws Throwable
     */
    public function generateToken(): string;

    /**
     * @throws Throwable
     */
    public function decodeToken(string $token): array;
}
