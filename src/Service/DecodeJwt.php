<?php

namespace App\Service;

class DecodeJwt
{
    /**
     * @param string $token
     * @return array
     */
    public function decode(string $token): array
    {
        $tokenParts = explode('.', $token);
        return json_decode(base64_decode($tokenParts[1]), true);
    }
}
