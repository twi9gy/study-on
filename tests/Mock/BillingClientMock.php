<?php


namespace App\Tests\Mock;

use App\Exception\BillingAuthException;
use App\Security\User;
use App\Service\BillingClient;

class BillingClientMock extends BillingClient
{
    public function auth(string $request): string
    {
        // Получаем запрос.
        $data = json_decode($request, true);
        // Если в запресе указан пользователь test, то отдаем токен.
        if ($data['username'] === 'test@gmail.com' && $data['password'] === 'test') {
            return $this->generateToken('test@gmail.com');
        }
        // Если в запресе указан администратор admin, то отдаем токен.
        if ($data['username'] === 'admin@gmail.com' && $data['password'] === 'super_admin') {
            return $this->generateToken('admin@gmail.com');
        }
        // иначе вызываем исключение билинга. Это тождественно тому, что сервис не нашел пользователя.
        throw new BillingAuthException('Пользователь не найден.');
    }

    public function register(string $request): void
    {
    }

    public function getCurrentUser(User $user): array
    {
        // Формируем ответ
        $response = [
            'username' => null,
            'balance' => null
        ];

        if ($user->getUsername() === 'test@gmail.com') {
            $response['username'] = 'test@gmail.com';
            $response['balance'] = 10;
        } elseif ($user->getUsername() === 'admin@gmail.com') {
            $response['username'] = 'test@gmail.com';
            $response['balance'] = 100;
        }

        return $response;
    }

    private function generateToken(string $username): string
    {
        $roles = null;
        if ($username === 'test@gmail.com') {
            $roles = ['ROLE_USER'];
        } elseif ($username === 'admin@gmail.com') {
            $roles = ["ROLE_SUPER_ADMIN", "ROLE_USER"];
        }
        $dataPayload = [
            'username' => $username,
            'roles' => $roles,
            'exp' => (new \DateTime('+ 1 hour'))->getTimestamp(),
        ];
        $payload = base64_encode(json_encode($dataPayload));
        return 'header.' . $payload . '.signature';
    }
}
