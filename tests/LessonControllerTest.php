<?php

namespace App\Tests;

use App\DataFixtures\CourseFixtures;
use App\Entity\Course;
use App\Entity\Lesson;

class LessonControllerTest extends AbstractTest
{
    /**
     * @var string
     */
    private $basePath = '/lessons';

    public function getPath(): string
    {
        return $this->basePath;
    }

    public function getFixtures(): array
    {
        return [CourseFixtures::class];
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
        // Получаем менеджер и репозиторий уроков
        $em = static::getEntityManager();
        $lessons = $em->getRepository(Lesson::class)->findAll();

        foreach ($lessons as $lesson) {
            // Создание запроса
            $client = static::getClient();
            $client->request('GET', $this->getPath() . '/' . $lesson->getId());

            // Проверка Http старуса ответа
            $this->assertResponseOk();
        }
    }

    // Тест удаления всех уроков из курса
    public function testLessonDelete(): void
    {
        // Создание запроса. Переходим на страницу курсов
        $client = static::getClient();
        $crawler = $client->request('GET', '/courses/');

        // Проверка Http старуса ответа
        $this->assertResponseOk();

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
        // Создание запроса. Переходим на страницу курсов
        $client = static::getClient();
        $crawler = $client->request('GET', '/courses/');

        // Проверка Http старуса ответа
        $this->assertResponseOk();

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
        // Создание запроса. Переходим на страницу курсов
        $client = static::getClient();
        $crawler = $client->request('GET', '/courses/');

        // Проверка Http старуса ответа
        $this->assertResponseOk();

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
        // Создание запроса. Переходим на страницу курсов
        $client = static::getClient();
        $crawler = $client->request('GET', '/courses/');

        // Проверка Http старуса ответа
        $this->assertResponseOk();

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
        // Создание запроса. Переходим на страницу курсов
        $client = static::getClient();
        $crawler = $client->request('GET', '/courses/');

        // Проверка Http старуса ответа
        $this->assertResponseOk();

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
        // Создание запроса. Переходим на страницу курсов
        $client = static::getClient();
        $crawler = $client->request('GET', '/courses/');

        // Проверка Http старуса ответа
        $this->assertResponseOk();

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
        // Создание запроса. Переходим на страницу курсов
        $client = static::getClient();
        $crawler = $client->request('GET', '/courses/');

        // Проверка Http старуса ответа
        $this->assertResponseOk();

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
        // Создание запроса. Переходим на страницу курсов
        $client = static::getClient();
        $crawler = $client->request('GET', '/courses/');

        // Проверка Http старуса ответа
        $this->assertResponseOk();

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
        // Создание запроса. Переходим на страницу курсов
        $client = static::getClient();
        $crawler = $client->request('GET', '/courses/');

        // Проверка Http старуса ответа
        $this->assertResponseOk();

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
