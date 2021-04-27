<?php

namespace App\Tests;

use App\DataFixtures\CourseFixtures;
use App\Entity\Course;
use phpDocumentor\Reflection\Types\This;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\SerializerInterface;

class HomeControllerTest extends AbstractTest
{
    private $serializer;


    public function getFixtures(): array
    {
        return [CourseFixtures::class];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->serializer = self::$container->get(SerializerInterface::class);
    }

    public function testAccessHomePage(): void
    {
        $client = self::getClient();

        // Получаем менеджер и репозиторий курсов
        $em = static::getEntityManager();
        $repository = $em->getRepository(Course::class);

        // Получаем курсы из study-on
        $courses = $repository->findAll();

        // Создание запроса
        $crawler = $client->request('GET', '/');

        // Проверка Http старуса ответа
        $this->assertResponseCode(Response::HTTP_OK, $client->getResponse());

        // Получение списка курсов
        $listCourse = $crawler->filter('div#list_course')->children();

        // Проверка количества курсов на странице
        static::assertEquals(count($courses), $listCourse->count());
    }
}
