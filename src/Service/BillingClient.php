<?php


namespace App\Service;

use App\Exception\BillingAuthException;
use App\Exception\BillingUnavailableException;
use App\Security\User;

class BillingClient
{
    private $baseUri;

    public function __construct()
    {
        $this->baseUri = $_ENV['BILLING_SERVICE'];
    }

    /**
     * @throws BillingUnavailableException
     * @throws BillingAuthException
     */
    public function auth(string $request): string
    {
        // Формирование запроса в сервис Billing
        $ch = curl_init($this->baseUri . '/api/v1/auth');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($request)
        ]);
        $response = curl_exec($ch);

        // Выбрасываем ошибку биллинга
        if (curl_exec($ch) === false) {
            throw new BillingUnavailableException('Сервис временно недоступен. 
            Попробуйте авторизоваться позднее.');
        }

        curl_close($ch);

        // Парсер ответа сервиса
        $result = json_decode($response, true);
        if (isset($result['code']) && $result['code'] === 401) {
            throw new BillingAuthException('Пользователь не найден.');
        }
        return $result['token'] ?? '';
    }

    /**
     * @throws BillingUnavailableException
     * @throws BillingAuthException
     */
    public function getCurrentUser(User $user): array
    {
        // Формирование запроса в сервис Billing
        $ch = curl_init($this->baseUri . '/api/v1/users/current');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $user->getApiToken()
        ]);
        $response = curl_exec($ch);

        // Выбрасываем ошибку биллинга
        if (curl_exec($ch) === false) {
            throw new BillingUnavailableException('Сервис временно недоступен. 
            Попробуйте авторизоваться позднее');
        }

        curl_close($ch);

        // Парсер ответа сервиса
        $result = json_decode($response, true);
        if (isset($result['code']) && $result['code'] === 401) {
            throw new BillingAuthException('Пользователь не авторизован.');
        }
        return $result;
    }


    /**
     * @throws BillingUnavailableException
     * @throws BillingAuthException
     */
    public function register(string $request): void
    {
        // Формирование запроса в сервис Billing
        $ch = curl_init($this->baseUri . '/api/v1/register');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($request)
        ]);
        $response = curl_exec($ch);

        // Выбрасываем ошибку биллинга
        if (curl_exec($ch) === false) {
            throw new BillingUnavailableException('Сервис временно недоступен. 
            Попробуйте зарегистироваться позднее.');
        }

        curl_close($ch);

        // Парсер ответа сервиса
        $result = json_decode($response, true);
        if (isset($result['code'])) {
            throw new BillingAuthException($result['message']);
        }
    }
}
