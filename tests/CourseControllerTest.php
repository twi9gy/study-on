<?php

namespace App\Tests;

use App\DataFixtures\CourseFixtures;
use App\Entity\Course;
use phpDocumentor\Reflection\Types\This;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\SerializerInterface;

class CourseControllerTest extends AbstractTest
{
    /**
     * @var string
     */
    private $basePath;
    private $serializer;
    private $redirectPath;

    public function getPath(): string
    {
        return $this->basePath;
    }

    public function getFixtures(): array
    {
        return [CourseFixtures::class];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->serializer = self::$container->get(SerializerInterface::class);
        $this->basePath = '/courses';
        $this->redirectPath = '/courses/';
    }

    /**
     * @dataProvider urlProviderSuccessful
     * @param $url
     */
    public function testPageIsSuccessful($url): void
    {
        // Тесты доступности страниц
        $client = self::getClient();
        $client->request('GET', $url);
        // Проверка ответа запроса (редирект на страницу авторизации)
        self::assertTrue($client->getResponse()->isRedirect('/login'));
    }

    public function urlProviderSuccessful(): \Generator
    {
        yield ['/courses/'];
        yield ['/courses/new'];
    }

    // Тесты несуществующих url курса
    public function testPageIsNotFound(): void
    {
        // Получаем менеджер и репозиторий курсов
        $em = static::getEntityManager();
        $lastCourse = $em->getRepository(Course::class)->findLastCourse();

        // Создание запроса
        $client = self::getClient();
        $client->request('GET', $this->getPath() . '/' . ($lastCourse->getId() + 10));

        // Проверка Http старуса ответа
        $this->assertResponseNotFound();
    }

    // Тесты для главной страницы курсов
    public function testCourseIndex(): void
    {
        // Формируем данные для авторизации
        $data = [
            'username' => 'test@gmail.com',
            'password' => 'general_user'
        ];
        $requestData = $this->serializer->serialize($data, 'json');

        // Авторизация пользователя и редирект на страницу курсов
        $crawler = $this->auth($requestData, $this->redirectPath);
        $client = self::getClient();

        // Получение списка курсов
        $listCourse = $crawler->filter('div#list_course')->children();

        // Проверка количества курсов на странице
        static::assertEquals(9, $listCourse->count());

        // Получение стоимосей курсов
        $coursesCost = $crawler->filter('.course_cost');

        // Получаем названия курсов
        $costList = $coursesCost->each(function (Crawler $node) {
            return $node->text();
        });

        // Проверка отображения стоимостей курсов
        static::assertEquals(7, count($costList));

        // Получение арендованных курсов
        $coursesRent = $crawler->filter('.course_rented');

        // Получаем названия курсов
        $rentList = $coursesRent->each(function (Crawler $node) {
            return $node->text();
        });

        // Проверка отображения арендованных курсов
        static::assertEquals(2, count($rentList));

        // Получение купленных курсов
        $coursesPurchased = $crawler->filter('p.course_purchased');

        // Получаем названия курсов
        $purchasedList = $coursesPurchased->each(function (Crawler $node) {
            return $node->text();
        });

        // Проверка отображения купленных курсов
        static::assertEquals(2, count($purchasedList));
    }

    // Тест для проверки недоступности списка курсов по ссылке без авторизации
    public function testAccessCourseIndex(): void
    {
        $client = self::getClient();
        $client->request('GET', $this->getPath() . '/');
        // Проверка Http старуса ответа
        $this->assertResponseCode(Response::HTTP_FOUND, $client->getResponse());
    }

    // Тест для проверки недоступности курсов по ссылке без авторизации
    public function testAccessCourseShow(): void
    {
        // Получаем менеджер и репозиторий уроков
        $em = static::getEntityManager();
        $courses = $em->getRepository(Course::class)->findAll();

        $client = self::getClient();

        foreach ($courses as $course) {
            $client->request('GET', $this->getPath() . '/' . $course->getId());
            // Проверка Http старуса ответа
            $this->assertResponseCode(Response::HTTP_FOUND, $client->getResponse());
        }
    }

    // Тесты страницы курсов
    public function testCourseShow(): void
    {
        // Формируем данные для авторизации
        $data = [
            'username' => 'test@gmail.com',
            'password' => 'general_user'
        ];
        $requestData = $this->serializer->serialize($data, 'json');

        // Авторизация пользователя и редирект на страницу курсов
        $this->auth($requestData, $this->redirectPath);
        $client = self::getClient();

        // Получаем менеджер и репозиторий курсов
        $em = static::getEntityManager();
        $repository = $em->getRepository(Course::class);

        // Получаем все курсы
        $courses = $repository->findAll();

        // Проходим все страницы курсов и проверяем количество уроков в курсе
        foreach ($courses as $course) {
            // Создание запроса
            $crawler = $client->request('GET', $this->getPath() . '/' . $course->getId());

            // Проверка Http старуса ответа
            $this->assertResponseCode(Response::HTTP_OK, $client->getResponse());

            // Получение списка уроков, которое отображается на странице
            $listLessons = $crawler->filter('ol#list_lessons')->children();

            // Проверка количества уроков в курсе
            static::assertEquals(count($course->getLessons()), $listLessons->count());
        }
    }

    // Тест удаления всех курсов с ролью администарора
    public function testCourseDeleteWithAdminRole(): void
    {
        // Формируем данные для авторизации
        $data = [
            'username' => 'admin@gmail.com',
            'password' => 'super_admin'
        ];
        $requestData = $this->serializer->serialize($data, 'json');

        // Авторизация пользователя и редирект на страницу курсов
        $crawler = $this->auth($requestData, $this->redirectPath);
        $client = self::getClient();

        while (true) {
            // Выбираем курс
            $course = $crawler->filter('a.course_title')->first()->link();

            // Переходим на страницу курса
            $crawler = $client->click($course);
            $this->assertResponseOk();

            // Нажимаем на кнопку "удалить"
            $deleteForm = $crawler->selectButton('btn_delete_course')->form();
            $client->submit($deleteForm);
            // Проверка ответа запроса (редирект на страницу со списком курсов)
            self::assertTrue($client->getResponse()->isRedirect($this->getPath() . '/'));

            // Редирект на страницу со списком курсов
            $crawler = $client->followRedirect();
            self::assertResponseOk();

            // Получаем менеджер и репозиторий курсов
            $em = static::getEntityManager();
            $listCourse = $em->getRepository(Course::class)->findAll();

            if (count($listCourse) === 0) {
                break;
            }
        }
    }

    // Тест недоступности удаления всех курсов с ролью пользователя
    public function testCourseDeleteWithUserRole()
    {
        // Тесты с использование интерфейса

        // Формируем данные для авторизации
        $data = [
            'username' => 'test@gmail.com',
            'password' => 'general_user'
        ];
        $requestData = $this->serializer->serialize($data, 'json');

        // Авторизация пользователя и редирект на страницу курсов
        $crawler = $this->auth($requestData, $this->redirectPath);
        $client = self::getClient();

        // Выбираем курс
        $courses = $crawler->filter('a.course_title');

        $coursesLink = $courses->each(function (Crawler $node) {
            return $node->link();
        });

        foreach ($coursesLink as $course) {
            // Переходим на страницу курса
            $crawler = $client->click($course);
            $this->assertResponseOk();

            // Ищем кнопку "удалить"
            $deleteForm = $crawler->selectButton('btn_delete_course');
            // Проверка: кнопка "удаить" не отображается
            self::assertEmpty($deleteForm);
        }

        // Конец тестов с использование интерфейса

        // Тесты по прямой ссылке

        // Получаем менеджер и репозиторий курсов
        $em = static::getEntityManager();
        $listCourse = $em->getRepository(Course::class)->findAll();

        foreach ($listCourse as $course) {
            $client = static::getClient();
            $crawler = $client->request('DELETE', $this->getPath() . '/' . $course->getId());

            // Проверка Http старуса ответа
            $this->assertResponseCode(Response::HTTP_FORBIDDEN, $client->getResponse());
        }

        // Конец тестов по прямой ссылке
    }

    // Тест страницы добавления курса с валидными значениями
    public function testCourseNewWithValidFields(): void
    {
        // Формируем данные для авторизации
        $data = [
            'username' => 'admin@gmail.com',
            'password' => 'super_admin'
        ];
        $requestData = $this->serializer->serialize($data, 'json');

        // Авторизация пользователя и редирект на страницу курсов
        $crawler = $this->auth($requestData, $this->redirectPath);
        $client = self::getClient();

        // Переходим на страницу формы
        $btn = $crawler->filter('a#add_course')->link();
        $crawler = $client->click($btn);
        $this->assertResponseOk();

        // Заполняем поля
        $form = $crawler->selectButton('btn_form_course')->form();
        $form['course[code]'] = 'F1L3M2O5L3A6';
        $form['course[title]'] = 'My first course';
        $form['course[description]'] = 'It`s my first course at this program.';

        // Отправляем форму с правильными значениями
        $client->submit($form);
        // Проверка ответа запроса (редирект на страницу со списком курсов)
        self::assertTrue($client->getResponse()->isRedirect($this->getPath() . '/'));

        // Редирект на страницу со списком курсов
        $crawler = $client->followRedirect();
        self::assertEquals($this->getPath() . '/', $client->getRequest()->getPathInfo());

        // Получение списка курсов
        $listCourse = $crawler->filter('div#list_course')->children();

        // Проверка обновленного количества курсов на странице (было 9)
        static::assertEquals(10, $listCourse->count());
    }

    // Тест недоступности создания курса с ролью пользователя
    public function testCourseNewWithUserRole()
    {
        // Тесты с использованием интерфейса

        // Формируем данные для авторизации
        $data = [
            'username' => 'test@gmail.com',
            'password' => 'general_user'
        ];
        $requestData = $this->serializer->serialize($data, 'json');

        // Авторизация пользователя и редирект на страницу курсов
        $crawler = $this->auth($requestData, $this->redirectPath);
        $client = self::getClient();

        // Переходим на страницу формы
        $createForm = $crawler->filter('a#add_course');
        // Проверка: кнопка "добавить курс" не отображается
        self::assertEmpty($createForm);

        // Конец тестов с использованием интерфейса

        // Тесты по прямой ссылке

        $client = static::getClient();
        $crawler = $client->request('GET', $this->getPath() . '/new');

        // Проверка Http старуса ответа
        $this->assertResponseCode(Response::HTTP_FORBIDDEN, $client->getResponse());

        // Конец тестов по прямой ссылке
    }

    // Тест страницы добавления курса с неправильными значениями поля код курса
    public function testCourseNewWithInvalidCodeField(): void
    {
        // Формируем данные для авторизации
        $data = [
            'username' => 'admin@gmail.com',
            'password' => 'super_admin'
        ];
        $requestData = $this->serializer->serialize($data, 'json');

        // Авторизация пользователя и редирект на страницу курсов
        $crawler = $this->auth($requestData, $this->redirectPath);
        $client = self::getClient();

        // Переходим на страницу формы
        $btn = $crawler->filter('a#add_course')->link();
        $crawler = $client->click($btn);
        $this->assertResponseOk();

        // Проверка пустого значения в поле code

        // Заполняем поля
        $form = $crawler->selectButton('btn_form_course')->form();
        $form['course[code]'] = '';
        $form['course[title]'] = 'My first course';
        $form['course[description]'] = 'It`s my first course at this program.';

        // Отправляем форму
        $crawler = $client->submit($form);
        // Получаем список ошибок
        $error = $crawler->filter('span.form-error-message')->first();
        self::assertEquals('Поле Код курса не должно быть пустым', $error->text());

        // Проверка уникальности code

        // Заполняем поля
        $form = $crawler->selectButton('btn_form_course')->form();
        $form['course[code]'] = 'Business-Analyst'; // Курс с таким кодом уже существует
        $form['course[title]'] = 'My first course';
        $form['course[description]'] = 'It`s my first course at this program.';


        // Отправляем форму
        $crawler = $client->submit($form);
        // Получаем ошибку
        $error = $crawler->filter('span.form-error-message')->first();

        self::assertEquals('Код курса должно быть уникальным', $error->text());
    }

    // Тест страницы добавления курса с неправильными значениями поля название курса
    public function testCourseNewWithInvalidTitleField(): void
    {
        // Формируем данные для авторизации
        $data = [
            'username' => 'admin@gmail.com',
            'password' => 'super_admin'
        ];
        $requestData = $this->serializer->serialize($data, 'json');

        // Авторизация пользователя и редирект на страницу курсов
        $crawler = $this->auth($requestData, $this->redirectPath);
        $client = self::getClient();

        // Переходим на страницу формы
        $btn = $crawler->filter('a#add_course')->link();
        $crawler = $client->click($btn);
        $this->assertResponseOk();

        // Проверка пустого значения в поле code

        // Заполняем поля
        $form = $crawler->selectButton('btn_form_course')->form();
        $form['course[code]'] = 'F1S2L7B8M4H7';
        $form['course[title]'] = '';
        $form['course[description]'] = 'It`s my first course at this program.';

        // Отправляем форму
        $crawler = $client->submit($form);
        // Получаем список ошибок
        $error = $crawler->filter('span.form-error-message')->first();
        self::assertEquals('Поле Название курса не должно быть пустым', $error->text());
    }

    // Тест загрузки данных в поля формы на странице изменения курса
    public function testCourseEditFillFields(): void
    {
        // Формируем данные для авторизации
        $data = [
            'username' => 'admin@gmail.com',
            'password' => 'super_admin'
        ];
        $requestData = $this->serializer->serialize($data, 'json');

        // Авторизация пользователя и редирект на страницу курсов
        $crawler = $this->auth($requestData, $this->redirectPath);
        $client = self::getClient();

        // Получаем менеджер и репозиторий курсов
        $em = static::getEntityManager();
        $listCourse = $em->getRepository(Course::class)->findAll();

        // Проходим в цикле все курсы и просматриваем заполнение полей в форме
        foreach ($listCourse as $course) {
            // Создание запроса. Переходим на страницу изменения курса
            $client = static::getClient();
            $crawler = $client->request('GET', $this->getPath() . '/' . $course->getId() . '/edit');

            // Проверка Http старуса ответа
            $this->assertResponseCode(Response::HTTP_OK, $client->getResponse());

            // Получаем форму
            $form = $crawler->selectButton('btn_form_course')->form();

            // Проверка заполненности полей в форме
            self::assertEquals($course->getCode(), $form['course[code]']->getValue());
            self::assertEquals($course->getTitle(), $form['course[title]']->getValue());
            self::assertEquals($course->getDescription(), $form['course[description]']->getValue());
        }
    }

    // Тест недоступности изменения всех курсов с ролью пользователя
    public function testCourseEditWithUserRole()
    {
        // Тесты с использованием интерфейса

        // Формируем данные для авторизации
        $data = [
            'username' => 'test@gmail.com',
            'password' => 'general_user'
        ];
        $requestData = $this->serializer->serialize($data, 'json');

        // Авторизация пользователя и редирект на страницу курсов
        $crawler = $this->auth($requestData, $this->redirectPath);
        $client = self::getClient();

        // Выбираем курс
        $courses = $crawler->filter('a.course_title');

        $coursesLink = $courses->each(function (Crawler $node) {
            return $node->link();
        });

        foreach ($coursesLink as $course) {
            // Переходим на страницу курса
            $crawler = $client->click($course);
            $this->assertResponseOk();

            // Ищем кнопку "редактировать"
            $editForm = $crawler->filter('a#edit_course')->first();
            // Проверка: кнопка "редактировать" не отображается
            self::assertEmpty($editForm);
        }

        // Конец тестов с использованием интерфейса

        // Тесты по прямой ссылке

        // Получаем менеджер и репозиторий курсов
        $em = static::getEntityManager();
        $listCourse = $em->getRepository(Course::class)->findAll();

        foreach ($listCourse as $course) {
            $client = static::getClient();
            $crawler = $client->request('GET', $this->getPath() . '/' . $course->getId() . '/edit');

            // Проверка Http старуса ответа
            $this->assertResponseCode(Response::HTTP_FORBIDDEN, $client->getResponse());
        }

        // Конец тестов по прямой ссылке
    }

    // Тест страницы изменения курса с валидными значениями
    public function testCourseEditWithValidFields(): void
    {
        // Формируем данные для авторизации
        $data = [
            'username' => 'admin@gmail.com',
            'password' => 'super_admin'
        ];
        $requestData = $this->serializer->serialize($data, 'json');

        // Авторизация пользователя и редирект на страницу курсов
        $crawler = $this->auth($requestData, $this->redirectPath);
        $client = self::getClient();

        // Выбираем курс
        $course = $crawler->filter('a.course_title')->first()->link();

        // Переходим на страницу редактирования курса
        $crawler = $client->click($course);
        $this->assertResponseOk();

        // Нажимаем на кнопку "редактировать"
        $editForm = $crawler->filter('a#edit_course')->link();
        $crawler = $client->click($editForm);
        $this->assertResponseOk();

        // Получаем форму
        $form = $crawler->selectButton('btn_form_course')->form();

        // Получаем менеджер и репозиторий курсов
        $em = static::getEntityManager();
        $course = $em->getRepository(Course::class)->findOneBy(['code' => $form['course[code]']->getValue()]);

        // Изменяем поля в форме
        $form['course[code]'] = 'H3K3V6M6S7';
        $form['course[title]'] = 'My second course';
        $form['course[description]'] = 'It`s my second course at this program.';

        // Отправляем форму с правильными значениями
        $client->submit($form);
        // Проверка ответа запроса (редирект на страницу курса)
        self::assertTrue($client->getResponse()->isRedirect($this->getPath() . '/' . $course->getId()));
    }

    // Тест страницы изменения курса с неправильными значениями поля код курса
    public function testCourseEditWithInvalidCodeField(): void
    {
        // Формируем данные для авторизации
        $data = [
            'username' => 'admin@gmail.com',
            'password' => 'super_admin'
        ];
        $requestData = $this->serializer->serialize($data, 'json');

        // Авторизация пользователя и редирект на страницу курсов
        $crawler = $this->auth($requestData, $this->redirectPath);
        $client = self::getClient();

        // Выбираем курс
        $course = $crawler->filter('a.course_title')->first()->link();

        // Переходим на страницу курса
        $crawler = $client->click($course);
        $this->assertResponseOk();

        // Нажимаем на кнопку "редактировать"
        $editForm = $crawler->filter('a#edit_course')->link();
        $crawler = $client->click($editForm);
        $this->assertResponseOk();

        // Проверка пустого значения в поле code

        // Заполняем поля
        $form = $crawler->selectButton('btn_form_course')->form();
        $form['course[code]'] = (string)'';

        // Отправляем форму
        $crawler = $client->submit($form);
        // Получаем список ошибок
        $error = $crawler->filter('span.form-error-message')->first();
        self::assertEquals('Поле Код курса не должно быть пустым', $error->text());

        // Проверка уникальности code

        // Заполняем поле
        $form = $crawler->selectButton('btn_form_course')->form();
        $form['course[code]'] = 'Business-Analyst'; // Курс с таким кодом уже существует

        // Отправляем форму
        $crawler = $client->submit($form);
        // Получаем ошибку
        $error = $crawler->filter('span.form-error-message')->first();
        self::assertEquals('Код курса должно быть уникальным', $error->text());
    }

    // Тест страницы изменения курса с неправильными значениями поля код курса
    public function testCourseEditWithInvalidTitleField(): void
    {
        // Формируем данные для авторизации
        $data = [
            'username' => 'admin@gmail.com',
            'password' => 'super_admin'
        ];
        $requestData = $this->serializer->serialize($data, 'json');

        // Авторизация пользователя и редирект на страницу курсов
        $crawler = $this->auth($requestData, $this->redirectPath);
        $client = self::getClient();

        // Выбираем курс
        $course = $crawler->filter('a.course_title')->first()->link();

        // Переходим на страницу курса
        $crawler = $client->click($course);
        $this->assertResponseOk();

        // Нажимаем на кнопку "редактировать"
        $editForm = $crawler->filter('a#edit_course')->link();
        $crawler = $client->click($editForm);
        $this->assertResponseOk();

        // Проверка пустого значения в поле title

        // Заполняем поле
        $form = $crawler->selectButton('btn_form_course')->form();
        $form['course[title]'] = '';

        // Отправляем форму
        $crawler = $client->submit($form);
        // Получаем список ошибок
        $error = $crawler->filter('span.form-error-message')->first();
        self::assertEquals('Поле Название курса не должно быть пустым', $error->text());
    }

    public function testCoursePay():void
    {
        $data = [
            'username' => 'test@gmail.com',
            'password' => 'general_user'
        ];
        $requestData = $this->serializer->serialize($data, 'json');

        // Авторизация пользователя и редирект на страницу курсов
        $crawler = $this->auth($requestData, $this->redirectPath);
        $client = self::getClient();

        // Получаем менеджер и репозиторий курсов
        $em = static::getEntityManager();
        $course = $em->getRepository(Course::class)->findOneBy(['code' => 'Introduction-to-Data-Analysis-and-Machine-Learning']);

        // Переходим на неоплаченный курс
        $crawler = $client->request('GET', $this->getPath() . '/' . $course->getId());

        // Нажимаем на кнопку "Арендовать"
        $BuyButton = $crawler->selectButton('Арендовать')->form();
        $crawler = $client->submit($BuyButton);

        // Нажимаем на кнопку "Да" в модальном окне
        $agreeButton = $crawler->selectButton('Да')->form();
        $crawler = $client->submit($agreeButton);

        // Проверка ответа запроса (редирект на страницу курса)
        self::assertTrue($client->getResponse()->isRedirect('/courses/' . $course->getId() . '/pay'));
    }
}
