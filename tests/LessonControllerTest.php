<?php

namespace App\Tests;

use App\DataFixtures\CourseFixtures;
use App\Entity\Course;
use App\Entity\Lesson;
use App\Service\BillingClient;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\SerializerInterface;

class LessonControllerTest extends AbstractTest
{
    /**
     * @var string
     */
    private $basePath;
    private $redirectPath;
    private $serializer;

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
        $this->basePath = '/lessons';
        $this->redirectPath = '/courses/';
    }

    // Тесты несуществующих url урока
    public function testPageIsNotFound(): void
    {
        // Получаем менеджер и репозиторий уроков
        $em = static::getEntityManager();
        $lastLesson = $em->getRepository(Lesson::class)->findLastLesson();

        // Создание запроса
        $client = self::getClient();
        $client->request('GET', $this->getPath() . '/' . ($lastLesson->getId() + 10));

        // Проверка Http старуса ответа
        $this->assertResponseNotFound();
    }

    // Тест перехода по страницам уроков
    public function testLessonShow(): void
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

        // Получаем менеджер и репозиторий уроков
        $em = static::getEntityManager();
        $lessons = $em->getRepository(Lesson::class)->findAll();

        foreach ($lessons as $lesson) {
            $client->request('GET', $this->getPath() . '/' . $lesson->getId());
            // Проверка Http старуса ответа
            // Пользователь test@gmail.com имеет доступ только к 3-ум курсам
            // 2 из которых платные и 1 бесплатный
            if ($lesson->getCourse()->getCode() === 'Business-Analyst' |
                $lesson->getCourse()->getCode() === 'Internet-Marketer' |
                $lesson->getCourse()->getCode() === 'Web-Designer') {
                // Проверка статуса ответа
                $this->assertResponseCode(200, $client->getResponse());
            }
//            else {
//                $this->assertResponseCode(500, $client->getResponse());
//            }
        }
    }

    // Тест для проверки недоступности страниц уроков по ссылке без авторизации
    public function testAccessLessonShow(): void
    {
        // Получаем менеджер и репозиторий уроков
        $em = static::getEntityManager();
        $lessons = $em->getRepository(Lesson::class)->findAll();

        $client = self::getClient();

        foreach ($lessons as $lesson) {
            $client->request('GET', $this->getPath() . '/' . $lesson->getId());
            // Проверка Http старуса ответа
            $this->assertResponseCode(Response::HTTP_FOUND, $client->getResponse());
        }
    }


    // Тест недоступности удаления всех уроков с ролью пользователя
    public function testLessonDeleteWithUserRole(): void
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

        // Выбираем курсы
        $courses = $crawler->filter('a.course_title');

        // Получаем ссылки на курсы
        $coursesLink = $courses->each(function (Crawler $node) {
            return $node->link();
        });

        // Получаем названия курсов
        $coursesTitle = $courses->each(function (Crawler $node) {
            return $node->text();
        });

        // Получем менеджер
        $em = static::getEntityManager();

        $iter = 0;
        foreach ($coursesLink as $course) {
            // Получаем информацию о курсе
            $courseData = $em->getRepository(Course::class)->findOneBy(['title' => $coursesTitle[$iter]]);

            // Если пользователь имеет доступ, то переходим в курс
            if ($courseData->getCode() === 'Business-Analyst' |
                $courseData->getCode() === 'Internet-Marketer' |
                $courseData->getCode() === 'Web-Designer') {
                // Проверка статуса ответа
                $this->assertResponseCode(200, $client->getResponse());

                // Переходим на страницу курса
                $crawler = $client->click($course);
                $this->assertResponseOk();

                // Выбираем уроки курса
                $lessons = $crawler->filter('a.lesson_title');

                $lessonsLink = $lessons->each(function (Crawler $node) {
                    return $node->link();
                });

                foreach ($lessonsLink as $lesson) {
                    // Переходим на страницу урока
                    $crawler = $client->click($lesson);
                    // Проверка статуса ответа
                    $this->assertResponseCode(200, $client->getResponse());
                    // Ищем кнопку "удалить"
                    $deleteForm = $crawler->selectButton('btn_delete_lesson');
                    // Проверка: кнопка "удаить" не отображается
                    self::assertEmpty($deleteForm);
                }
            }
            ++$iter;
        }

        // Конец тестов с использование интерфейса

        // Тесты по прямой ссылке

        // Получаем менеджер и репозиторий курсов
        $em = static::getEntityManager();
        $lessons = $em->getRepository(Lesson::class)->findAll();

        foreach ($lessons as $lesson) {
            $client = static::getClient();
            $client->request('DELETE', $this->getPath() . '/' . $lesson->getId());
            // Проверка Http старуса ответа
            $this->assertResponseCode(Response::HTTP_FORBIDDEN, $client->getResponse());
        }

        // Конец тестов по прямой ссылке
    }

    // Тест недоступности создания уроков с ролью пользователя
    public function testLessonNewWithUserRole(): void
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


        // Получаем названия курсов
        $coursesTitle = $courses->each(function (Crawler $node) {
            return $node->text();
        });

        // Получем менеджер
        $em = static::getEntityManager();

        $iter = 0;
        foreach ($coursesLink as $course) {
            // Получаем информацию о курсе
            $courseData = $em->getRepository(Course::class)->findOneBy(['title' => $coursesTitle[$iter]]);

            // Если пользователь имеет доступ, то переходим в курс
            if ($courseData->getCode() === 'Business-Analyst' |
                $courseData->getCode() === 'Internet-Marketer' |
                $courseData->getCode() === 'Web-Designer') {
                // Проверка статуса ответа
                $this->assertResponseCode(200, $client->getResponse());

                // Переходим на страницу курса
                $crawler = $client->click($course);
                $this->assertResponseOk();

                // Выбираем уроки курса
                $lessons = $crawler->filter('a.lesson_title');

                $lessonsLink = $lessons->each(function (Crawler $node) {
                    return $node->link();
                });

                foreach ($lessonsLink as $lesson) {
                    // Переходим на страницу урока
                    $crawler = $client->click($lesson);
                    // Проверка статуса ответа
                    $this->assertResponseCode(200, $client->getResponse());
                    // Ищем кнопку "добавить урок"
                    $addForm = $crawler->filter('a#add_lesson');
                    // Проверка: кнопка "добавить урок" не отображается
                    self::assertEmpty($addForm);
                }
            }
            ++$iter;
        }

        // Конец тестов с использованием интерфейса

        // Тесты по прямой ссылке
//
//        $client = static::getClient();
//        $crawler = $client->request('GET', $this->getPath() . '/new');
//
//        // Проверка Http старуса ответа
//        $this->assertResponseCode(Response::HTTP_FORBIDDEN, $client->getResponse());

        // Конец тестов по прямой ссылке
    }


    // Тест недоступности изменения всех уроков с ролью пользователя
    public function testLessonEditWithUserRole(): void
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

        // Получаем названия курсов
        $coursesTitle = $courses->each(function (Crawler $node) {
            return $node->text();
        });

        // Получем менеджер
        $em = static::getEntityManager();

        $iter = 0;
        foreach ($coursesLink as $course) {
            // Получаем информацию о курсе
            $courseData = $em->getRepository(Course::class)->findOneBy(['title' => $coursesTitle[$iter]]);

            // Если пользователь имеет доступ, то переходим в курс
            if ($courseData->getCode() === 'Business-Analyst' |
                $courseData->getCode() === 'Internet-Marketer' |
                $courseData->getCode() === 'Web-Designer') {
                // Проверка статуса ответа
                $this->assertResponseCode(200, $client->getResponse());

                // Переходим на страницу курса
                $crawler = $client->click($course);
                $this->assertResponseOk();

                // Выбираем уроки курса
                $lessons = $crawler->filter('a.lesson_title');

                $lessonsLink = $lessons->each(function (Crawler $node) {
                    return $node->link();
                });

                foreach ($lessonsLink as $lesson) {
                    // Переходим на страницу урока
                    $crawler = $client->click($lesson);
                    // Проверка статуса ответа
                    $this->assertResponseCode(200, $client->getResponse());
                    // Ищем кнопку "редактировать"
                    $editForm = $crawler->filter('a#edit_lesson');
                    // Проверка: кнопка "редактировать" не отображается
                    self::assertEmpty($editForm);
                }
            }
            ++$iter;
        }

        // Конец тестов с использованием интерфейса

        // Тесты по прямой ссылке

        // Получаем менеджер и репозиторий курсов
//        $em = static::getEntityManager();
//        $lessons = $em->getRepository(Lesson::class)->findAll();
//
//        foreach ($lessons as $lesson) {
//            $client = static::getClient();
//            $client->request('GET', $this->getPath() . '/' . $lesson->getId() . '/edit');
//            // Проверка Http старуса ответа
//            $this->assertResponseCode(Response::HTTP_FORBIDDEN, $client->getResponse());
//        }

        // Конец тестов по прямой ссылке
    }

    // Тест удаления всех уроков из курса
    public function testLessonDeleteWithRoleAdmin(): void
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
        $course_link = $crawler->filter('a.course_title')->first()->link();

        // Получаем менеджер и репозиторий курсов
        $em = static::getEntityManager();
        $course = $em->getRepository(Course::class)->findOneBy([
            'title' => $crawler->filter('a.course_title')->first()->text()
        ]);
        self::assertNotEmpty($course);

        // Переходим на страницу курса
        $crawler = $client->click($course_link);
        $this->assertResponseOk();

        while (true) {
            // Выбираем урок
            $lesson = $crawler->filter('a.lesson_title')->first()->link();

            // Переходим на страницу урока
            $crawler = $client->click($lesson);
            $this->assertResponseOk();

            // Нажимаем на кнопку "удалить"
            $deleteForm = $crawler->selectButton('btn_delete_lesson')->form();
            $client->submit($deleteForm);
            // Проверка ответа запроса (редирект на страницу курса)
            self::assertTrue($client->getResponse()->isRedirect('/courses/' . $course->getId()));

            // Редирект на страницу курса
            $crawler = $client->followRedirect();
            $this->assertResponseOk();

            // Получаем менеджер и репозиторий уроков
            $em = static::getEntityManager();
            $listLesson = $em->getRepository(Lesson::class)->findBy(['course' => $course->getId()]);

            if (count($listLesson) === 0) {
                break;
            }
        }
    }

    // Тест создания урока с валидными полями
    public function testLessonNewWithValidFields(): void
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

        // Нажимаем на кнопку "Добавить урок"
        $lessonCreate = $crawler->filter('a#add_lesson')->link();
        $crawler = $client->click($lessonCreate);
        $this->assertResponseOk();

        // Заполняем поля
        $form = $crawler->selectButton('btn_form_lesson')->form();
        $form['lesson[title]'] = 'First lesson';
        $form['lesson[content]'] = 'It`s my first lesson';
        $form['lesson[number]'] = '20';

        // Получаем менеджер и репозиторий уроков
        $em = static::getEntityManager();
        $course = $em->getRepository(Course::class)->findOneBy(['id' => $form['lesson[course]']->getValue()]);
        self::assertNotEmpty($course);

        // Отправляем форму
        $client->submit($form);
        self::assertTrue($client->getResponse()->isRedirect('/courses/' . $course->getId()));

        // Редирект на страницу курса
        $crawler = $client->followRedirect();
        $this->assertResponseOk();

        // Получение списка уроков, которое отображается на странице
        $listLessons = $crawler->filter('ol#list_lessons')->children();

        // Проверка количества уроков в курсе
        static::assertEquals(count($course->getLessons()), $listLessons->count());
    }

    // Тест создания урока с неверным заполнением поля Title
    public function testLessonNewWithInvalidTitleField(): void
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

        // Нажимаем на кнопку "Добавить урок"
        $lessonCreate = $crawler->filter('a#add_lesson')->link();
        $crawler = $client->click($lessonCreate);
        $this->assertResponseOk();

        // Заполняем поля
        $form = $crawler->selectButton('btn_form_lesson')->form();
        $form['lesson[title]'] = '';
        $form['lesson[content]'] = 'It`s my first lesson';
        $form['lesson[number]'] = '20';

        // Отправляем форму
        $crawler = $client->submit($form);
        $error = $crawler->filter('span.form-error-message')->first();
        self::assertEquals('Поле Название урока не должно быть пустым', $error->text());
    }

    // Тест создания урока с неверным заполнением поля Content
    public function testLessonNewWithInvalidContentField(): void
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

        // Нажимаем на кнопку "Добавить урок"
        $lessonCreate = $crawler->filter('a#add_lesson')->link();
        $crawler = $client->click($lessonCreate);
        $this->assertResponseOk();

        // Заполняем поля
        $form = $crawler->selectButton('btn_form_lesson')->form();
        $form['lesson[title]'] = 'First lesson';
        $form['lesson[content]'] = '';
        $form['lesson[number]'] = '20';

        // Отправляем форму
        $crawler = $client->submit($form);
        $error = $crawler->filter('span.form-error-message')->first();
        self::assertEquals('Поле Содержание не должно быть пустым', $error->text());
    }

    // Тест создания урока с неверным заполнением поля Порядковый номер
    public function testLessonNewWithInvalidNumberField(): void
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

        // Нажимаем на кнопку "Добавить урок"
        $lessonCreate = $crawler->filter('a#add_lesson')->link();
        $crawler = $client->click($lessonCreate);
        $this->assertResponseOk();

        // Проверка на пустоту поля

        // Заполняем поля
        $form = $crawler->selectButton('btn_form_lesson')->form();
        $form['lesson[title]'] = 'First lesson';
        $form['lesson[content]'] = 'It`s my first lesson';
        $form['lesson[number]'] = '';

        // Отправляем форму
        $crawler = $client->submit($form);
        $error = $crawler->filter('span.form-error-message')->first();
        self::assertEquals('Поле Порядковый номер не должно быть пустым', $error->text());

        // Проверка значения поля. Оно не должно быть больше 10000

        // Заполняем поле
        $form['lesson[number]'] = 10001;

        // Отправляем форму
        $crawler = $client->submit($form);
        $error = $crawler->filter('span.form-error-message')->first();
        self::assertEquals('Порядковый номер должен быть меньше 10000', $error->text());

        // Проверка значения поля. Оно не должно быть положительным числом

        // Заполняем поле
        $form['lesson[number]'] = -10001;

        // Отправляем форму
        $crawler = $client->submit($form);
        $error = $crawler->filter('span.form-error-message')->first();
        self::assertEquals('Порядковый номер должен быть положительным числом', $error->text());
    }

    // Тест редактирования урока при верно заполненных полях формы
    public function testLessonEditWithValidFields(): void
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

        // Выбираем урок
        $lesson = $crawler->filter('a.lesson_title')->first()->link();

        // Переходим на страницу урока
        $crawler = $client->click($lesson);
        $this->assertResponseOk();

        // Нажимаем на кнопку "редактировать"
        $editPage = $crawler->filter('a#edit_lesson')->link();
        $crawler = $client->click($editPage);
        $this->assertResponseOk();

        // Получаем форму
        $form = $crawler->selectButton('btn_form_lesson')->form();
        $form['lesson[title]'] = 'Second lesson';
        $form['lesson[content]'] = 'It`s my second lesson';
        $form['lesson[number]'] = '40';

        // Получаем менеджер и репозиторий уроков
        $em = static::getEntityManager();
        $course = $em->getRepository(Course::class)->findOneBy(['id' => $form['lesson[course]']->getValue()]);
        self::assertNotEmpty($course);

        // Отправляем форму
        $client->submit($form);
        self::assertTrue($client->getResponse()->isRedirect('/courses/' . $course->getId()));
    }

    // Тест редактирования урока при неверно заполненнои поле Title
    public function testLessonEditWithInvalidTitleField(): void
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

        // Выбираем урок
        $lesson = $crawler->filter('a.lesson_title')->first()->link();

        // Переходим на страницу урока
        $crawler = $client->click($lesson);
        $this->assertResponseOk();

        // Нажимаем на кнопку "редактировать"
        $editPage = $crawler->filter('a#edit_lesson')->link();
        $crawler = $client->click($editPage);
        $this->assertResponseOk();

        // Получаем форму
        $form = $crawler->selectButton('btn_form_lesson')->form();
        $form['lesson[title]'] = '';

        // Отправляем форму
        $crawler = $client->submit($form);
        $error = $crawler->filter('span.form-error-message')->first();
        self::assertEquals('Поле Название урока не должно быть пустым', $error->text());
    }

    // Тест редактирования урока при неверно заполненнои поле Content
    public function testLessonEditWithInvalidContentField(): void
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

        // Выбираем урок
        $lesson = $crawler->filter('a.lesson_title')->first()->link();

        // Переходим на страницу урока
        $crawler = $client->click($lesson);
        $this->assertResponseOk();

        // Нажимаем на кнопку "редактировать"
        $editPage = $crawler->filter('a#edit_lesson')->link();
        $crawler = $client->click($editPage);
        $this->assertResponseOk();

        // Получаем форму
        $form = $crawler->selectButton('btn_form_lesson')->form();
        $form['lesson[content]'] = '';

        // Отправляем форму
        $crawler = $client->submit($form);
        $error = $crawler->filter('span.form-error-message')->first();
        self::assertEquals('Поле Содержание не должно быть пустым', $error->text());
    }

    // Тест редактирования урока при неверно заполненнои поле Content
    public function testLessonEditWithInvalidNumberField(): void
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

        // Выбираем урок
        $lesson = $crawler->filter('a.lesson_title')->first()->link();

        // Переходим на страницу урока
        $crawler = $client->click($lesson);
        $this->assertResponseOk();

        // Нажимаем на кнопку "редактировать"
        $editPage = $crawler->filter('a#edit_lesson')->link();
        $crawler = $client->click($editPage);
        $this->assertResponseOk();

        // Проверка на пустоту поля

        // Получаем форму
        $form = $crawler->selectButton('btn_form_lesson')->form();
        $form['lesson[number]'] = '';

        // Отправляем форму
        $crawler = $client->submit($form);
        $error = $crawler->filter('span.form-error-message')->first();
        self::assertEquals('Поле Порядковый номер не должно быть пустым', $error->text());

        // Проверка значения поля. Оно не должно быть больше 10000

        // Заполняем поле
        $form['lesson[number]'] = 10001;

        // Отправляем форму
        $crawler = $client->submit($form);
        $error = $crawler->filter('span.form-error-message')->first();
        self::assertEquals('Порядковый номер должен быть меньше 10000', $error->text());

        // Проверка значения поля. Оно не должно быть положительным числом

        // Заполняем поле
        $form['lesson[number]'] = -10001;

        // Отправляем форму
        $crawler = $client->submit($form);
        $error = $crawler->filter('span.form-error-message')->first();
        self::assertEquals('Порядковый номер должен быть положительным числом', $error->text());
    }
}
