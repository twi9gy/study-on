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
            'password' => 'general_user'
        ];
        $requestData = $this->serializer->serialize($data, 'json');

        // Авторизация пользователя и редирект на страницу курсов
        $crawler = $this->auth($requestData, '/courses/');
        $client = self::getClient();

        // Переходим на страницу профиля
        $btn = $crawler->filter('a#private_office')->link();
        $crawler = $client->click($btn);
        $this->assertResponseOk();

        $username = $crawler->filter('#email');
        $roles = $crawler->filter('#roles');
        $balance = $crawler->filter('#balance');

        // Проверяем данные на странице профиля
        self::assertEquals('test@gmail.com', $username->attr('value'));
        self::assertEquals('Пользователь', $roles->attr('value'));
        self::assertEquals('10', $balance->attr('value'));
    }

    public function testAccessTransactionHistory(): void
    {
        // Формируем данные для авторизации
        $data = [
            'username' => 'test@gmail.com',
            'password' => 'general_user'
        ];
        $requestData = $this->serializer->serialize($data, 'json');

        // Авторизация пользователя и редирект на страницу курсов
        $crawler = $this->auth($requestData, '/courses/');
        $client = self::getClient();

        // Переходим на страницу профиля
        $btn = $crawler->filter('a#private_office')->link();
        $crawler = $client->click($btn);
        $this->assertResponseOk();

        // Выбираем ссылку на историю транзакций
        $transactionHistLink = $crawler->filter('a.transaction_history')->link();
        // Переходим на страницу истории транзакций
        $crawler = $client->click($transactionHistLink);
        $this->assertResponseOk();

        $table = $crawler->filter('.table')->first();
        // В таблице должно быть 2 записи (пополнение счета, покупка курса)
        self::assertEquals(2, $table->children()->count());
    }
}
