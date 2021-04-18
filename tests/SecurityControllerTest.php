<?php

namespace App\Tests;

use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Serializer\SerializerInterface;

class SecurityControllerTest extends AbstractTest
{
    private $serializer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->serializer = self::$container->get(SerializerInterface::class);
    }

    // Тестирование формы для регистрации (правильное заполнение).
    public function testRegisterWithValidFields(): void
    {
        // Переходим на страницу регистрации
        $client = static::getClient();
        $crawler = $client->request('GET', '/register');
        // Проверка Http старуса ответа
        $this->assertResponseOk();

        $form = $crawler->selectButton('Зерегистрироваться')->form();
        $form['register[email]'] = 'vadim@mail.ru';
        $form['register[password][first]'] = '123456';
        $form['register[password][second]'] = '123456';

        // Отправляем форму с правильными значениями
        $crawler = $client->submit($form);
        // Получаем список ошибок
        $errors = $crawler->filter('span.form-error-message');
        self::assertCount(0, $errors);
    }

    // Тестирование формы для регистрации (неправильное заполнение).
    public function testRegisterWithInValidFields(): void
    {
        // Переходим на страницу регистрации
        $client = static::getClient();
        $crawler = $client->request('GET', '/register');
        // Проверка Http старуса ответа
        $this->assertResponseOk();

        // Заполнение формы с пустыми значениями
        $form = $crawler->selectButton('Зерегистрироваться')->form();
        $form['register[email]'] = '';
        $form['register[password][first]'] = '';
        $form['register[password][second]'] = '';

        // Отправляем форму
        $crawler = $client->submit($form);
        // Получаем список ошибок
        $errors = $crawler->filter('span.form-error-message');
        self::assertCount(2, $errors);

        // Получение текста ошибок
        $errorsValues = $errors->each(function (Crawler $node) {
            return $node->text();
        });
        // Проверка сообщений
        self::assertEquals('Введите Email.', $errorsValues[0]);
        self::assertEquals('Введите пароль.', $errorsValues[1]);

        // Заполнение формы с неправильным Email и коротким паролем
        $form['register[email]'] = 'vadim';
        $form['register[password][first]'] = '123';
        $form['register[password][second]'] = '123';

        // Отправляем форму
        $crawler = $client->submit($form);
        // Получаем список ошибок
        $errors = $crawler->filter('span.form-error-message');
        self::assertCount(2, $errors);

        // Получение текста ошибок
        $errorsValues = $errors->each(function (Crawler $node, $i) {
            return $node->text();
        });
        // Проверка сообщений
        self::assertEquals('Неверно указан Email.', $errorsValues[0]);
        self::assertEquals('Ваш пароль должен состоять из 6 символов.', $errorsValues[1]);

        // Заполнение формы с разными паролями
        $form['register[email]'] = 'vadim@mail.ru';
        $form['register[password][first]'] = '123456';
        $form['register[password][second]'] = '123';

        // Отправляем форму
        $crawler = $client->submit($form);
        // Получаем список ошибок
        $errors = $crawler->filter('span.form-error-message');
        self::assertCount(1, $errors);

        // Получение текста ошибок
        $errorsValues = $errors->each(function (Crawler $node, $i) {
            return $node->text();
        });
        // Проверка сообщений
        self::assertEquals('Пароли не совпадают.', $errorsValues[0]);
    }
}
