<?php


namespace App\Service;

use App\Entity\Course;
use App\Exception\BillingAuthException;
use App\Exception\BillingUnavailableException;
use App\Security\User;
use Symfony\Component\Serializer\SerializerInterface;

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
    public function auth(string $request): array
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
        if ($response === false) {
            throw new BillingUnavailableException('Сервис временно недоступен. 
            Попробуйте авторизоваться позднее.');
        }

        curl_close($ch);

        // Парсер ответа сервиса
        $result = json_decode($response, true);
        if (isset($result['code']) && $result['code'] === 401) {
            throw new BillingAuthException('Пользователь не найден.');
        }
        return $result;
    }

    /**
     * @throws BillingUnavailableException
     * @throws BillingAuthException
     */
    public function getCurrentUser(User $user, DecodeJwt $decodeJwt, SerializerInterface $serializer): array
    {
        // Получаем полезную нагрузку из jwt token
        $payload = $decodeJwt->decode($user->getApiToken());

        // Получаем текущее время
        $date = date_create('now');
        // Сравниваем время окончания действия jwt token и текущего в формате timestamp
        if ($date->getTimestamp() >= $payload['exp']) {
            // Формируем данные для запроса в сервис оплаты
            $data = [
                'refresh_token' => $user->getRefreshToken(),
            ];
            $requestData = $serializer->serialize($data, 'json');
            // Обновляем токен
            $newTokens = $this->refreshUser($requestData);
            $user->setApiToken($newTokens['token']);
            $user->setRefreshToken($newTokens['refresh_token']);
        }

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
        if ($response === false) {
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
     */
    public function register(string $request): array
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
        if ($response === false) {
            throw new BillingUnavailableException('Сервис временно недоступен. 
            Попробуйте зарегистироваться позднее.');
        }

        curl_close($ch);

        // Парсер ответа сервиса
        return json_decode($response, true);
    }

    /**
     * @param string $refresh_token
     * @return mixed
     * @throws \App\Exception\BillingUnavailableException
     */
    public function refreshUser(string $refresh_token): array
    {
        // Формирование запроса в сервис Billing
        $ch = curl_init($this->baseUri . '/api/v1/token/refresh');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $refresh_token);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($refresh_token)
        ]);
        $response = curl_exec($ch);

        // Выбрасываем ошибку биллинга
        if ($response === false) {
            throw new BillingUnavailableException('Сервис временно недоступен.');
        }

        curl_close($ch);

        // Парсер ответа сервиса
        return json_decode($response, true);
    }

    /**
     * @throws BillingUnavailableException
     */
    public function getCourses(User $user):  array
    {
        // Формирование запроса в сервис Billing
        $ch = curl_init($this->baseUri . '/api/v1/courses/');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $user->getApiToken()
        ]);
        $response = curl_exec($ch);

        // Выбрасываем ошибку биллинга
        if ($response === false) {
            throw new BillingUnavailableException('Сервис временно недоступен. 
            Попробуйте авторизоваться позднее');
        }

        curl_close($ch);

        // Парсер ответа сервиса
        return json_decode($response, true);
    }

    /**
     * @throws BillingUnavailableException
     * @throws BillingAuthException
     */
    public function getUserCourses(User $user): array
    {
        // Формирование запроса в сервис Billing
        $ch = curl_init($this->baseUri . '/api/v1/users/courses');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $user->getApiToken()
        ]);
        $response = curl_exec($ch);

        // Выбрасываем ошибку биллинга
        if ($response === false) {
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
    public function getCourse(User $user, Course $course): array
    {
        // Формирование запроса в сервис Billing
        $ch = curl_init($this->baseUri . '/api/v1/courses/' . $course->getCode());
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $user->getApiToken()
        ]);
        $response = curl_exec($ch);

        // Выбрасываем ошибку биллинга
        if ($response === false) {
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
     */
    public function paymentCourse(User $user, Course $course): array
    {
        // Формирование запроса в сервис Billing
        $ch = curl_init($this->baseUri . '/api/v1/courses/' . $course->getCode() . '/pay');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $user->getApiToken()
        ]);
        $response = curl_exec($ch);

        // Выбрасываем ошибку биллинга
        if ($response === false) {
            throw new BillingUnavailableException('Сервис временно недоступен.');
        }

        curl_close($ch);

        // Парсер ответа сервиса
        return json_decode($response, true);
    }

    /**
     * @throws \App\Exception\BillingUnavailableException
     * @throws \App\Exception\BillingAuthException
     */
    public function getTransactions(User $user): array
    {
        // Формирование запроса в сервис Billing
        $ch = curl_init($this->baseUri . '/api/v1/transactions/');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $user->getApiToken()
        ]);
        $response = curl_exec($ch);

        // Выбрасываем ошибку биллинга
        if ($response === false) {
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
}
