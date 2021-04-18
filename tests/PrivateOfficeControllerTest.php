<?php

namespace App\Tests;

use Symfony\Component\Serializer\SerializerInterface;

class PrivateOfficeControllerTest extends AbstractTest
{
    private $serializer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->serializer = self::$container->get(SerializerInterface::class);
    }

    public function testAccessPrivateOffice(): void
    {
        // Формируем данные для авторизации
        $data = [
            'username' => 'test@gmail.com',
            'password' => 'test'
        ];
        $requestData = $this->serializer->serialize($data, 'json');

        // Авторизация пользователя и редирект на страницу курсов
        $crawler = $this->auth($requestData, '/courses/');
        $client = self::getClient();

        // Переходим на страницу профиля
        $btn = $crawler->filter('a#private_office')->link();
        $crawler = $client->click($btn);
        $this->assertResponseOk();

        $username = $crawler->filter('p#email');
        $roles = $crawler->filter('p#roles');
        $balance = $crawler->filter('p#balance');

        // Проверяем данные на странице профиля
        self::assertEquals('test@gmail.com', $username->text());
        self::assertEquals('Пользователь', $roles->text());
        self::assertEquals('10', $balance->text());
    }
}
