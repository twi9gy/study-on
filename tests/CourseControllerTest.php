<?php

namespace App\Tests;

use App\DataFixtures\CourseFixtures;
use App\Entity\Course;
use phpDocumentor\Reflection\Types\This;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Response;

class CourseControllerTest extends AbstractTest
{
    /**
     * @var string
     */
    private $basePath = '/courses';

    public function getPath(): string
    {
        return $this->basePath;
    }

    public function getFixtures(): array
    {
        return [CourseFixtures::class];
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
        self::assertResponseIsSuccessful();
    }

    public function urlProviderSuccessful(): \Generator
    {
        yield [$this->getPath() . '/'];
        yield [$this->getPath() . '/new'];
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
        // Создание запроса
        $client = static::getClient();
        $crawler = $client->request('GET', $this->getPath() . '/');

        // Проверка Http старуса ответа
        $this->assertResponseCode(Response::HTTP_OK, $client->getResponse());

        // Получение списка курсов
        $listCourse = $crawler->filter('div#list_course')->children();

        // Проверка количества курсов на странице
        static::assertEquals(4, $listCourse->count());
    }

    // Тесты страницы курса
    public function testCourseShow(): void
    {
        // Получаем менеджер и репозиторий курсов
        $em = static::getEntityManager();
        $repository = $em->getRepository(Course::class);

        // Получаем все курсы
        $courses = $repository->findAll();

        // Проходим все страницы курсов и проверяем количество уроков в курсе
        foreach ($courses as $course) {
            // Создание запроса
            $client = static::getClient();
            $crawler = $client->request('GET', $this->getPath() . '/' . $course->getId());

            // Проверка Http старуса ответа
            $this->assertResponseCode(Response::HTTP_OK, $client->getResponse());

            // Получение списка уроков, которое отображается на странице
            $listLessons = $crawler->filter('ol#list_lessons')->children();

            // Проверка количества уроков в курсе
            static::assertEquals(count($course->getLessons()), $listLessons->count());
        }
    }

    // Тест удаления всех курсов
    public function testCourseDelete(): void
    {
        // Создание запроса. Переходим на страницу курсов
        $client = static::getClient();
        $crawler = $client->request('GET', $this->getPath() . '/');

        // Проверка Http старуса ответа
        $this->assertResponseOk();

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

    // Тест страницы добавления курса с валидными значениями
    public function testCourseNewWithValidFields(): void
    {
        // Создание запроса. Переходим на страницу курсов
        $client = static::getClient();
        $crawler = $client->request('GET', $this->getPath() . '/');

        // Проверка Http старуса ответа
        $this->assertResponseCode(Response::HTTP_OK, $client->getResponse());

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

        // Проверка обновленного количества курсов на странице (было 4)
        static::assertEquals(5, $listCourse->count());
    }

    // Тест страницы добавления курса с неправильными значениями поля код курса
    public function testCourseNewWithInvalidCodeField(): void
    {
        // Создание запроса. Переходим на страницу курсов
        $client = static::getClient();
        $crawler = $client->request('GET', $this->getPath() . '/');

        // Проверка Http старуса ответа
        $this->assertResponseCode(Response::HTTP_OK, $client->getResponse());

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
        $form['course[code]'] = '1FFQQ2GF2'; // Курс с таким кодом уже существует
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
        // Создание запроса. Переходим на страницу курсов
        $client = static::getClient();
        $crawler = $client->request('GET', $this->getPath() . '/');

        // Проверка Http старуса ответа
        $this->assertResponseCode(Response::HTTP_OK, $client->getResponse());

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

    // Тест страницы изменения курса с валидными значениями
    public function testCourseEditWithValidFields(): void
    {
        // Создание запроса. Переходим на страницу курсов
        $client = static::getClient();
        $crawler = $client->request('GET', $this->getPath() . '/');

        // Проверка Http старуса ответа
        $this->assertResponseCode(Response::HTTP_OK, $client->getResponse());

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
        // Создание запроса. Переходим на страницу курсов
        $client = static::getClient();
        $crawler = $client->request('GET', $this->getPath() . '/');

        // Проверка Http старуса ответа
        $this->assertResponseOk();

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
        $form['course[code]'] = '2CCCA2F33'; // Курс с таким кодом уже существует

        // Отправляем форму
        $crawler = $client->submit($form);
        // Получаем ошибку
        $error = $crawler->filter('span.form-error-message')->first();
        self::assertEquals('Код курса должно быть уникальным', $error->text());
    }

    // Тест страницы изменения курса с неправильными значениями поля код курса
    public function testCourseEditWithInvalidTitleField(): void
    {
        // Создание запроса. Переходим на страницу курсов
        $client = static::getClient();
        $crawler = $client->request('GET', $this->getPath() . '/');

        // Проверка Http старуса ответа
        $this->assertResponseOk();

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
}
