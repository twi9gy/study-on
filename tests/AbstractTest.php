<?php

declare(strict_types=1);

namespace App\Tests;

use App\Service\BillingClient;
use App\Tests\Mock\BillingClientMock;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Response;

abstract class AbstractTest extends WebTestCase
{
    /** @var Client */
    protected static $client;

    protected static function getClient($reinitialize = false, array $options = [], array $server = [])
    {
        if (!static::$client || $reinitialize) {
            static::$client = static::createClient($options, $server);
        }

        // core is loaded (for tests without calling of getClient(true))
        static::$client->getKernel()->boot();

        return static::$client;
    }

    protected function setUp(): void
    {
        static::getClient();
        $this->loadFixtures($this->getFixtures());
    }

    final protected function tearDown(): void
    {
        parent::tearDown();
        static::$client = null;
    }

    /**
     * Shortcut
     */
    protected static function getEntityManager()
    {
        return static::$container->get('doctrine')->getManager();
    }

    /**
     * List of fixtures for certain test
     */
    protected function getFixtures(): array
    {
        return [];
    }

    /**
     * Load fixtures before test
     * @param array $fixtures
     */
    protected function loadFixtures(array $fixtures = []): void
    {
        $loader = new Loader();

        foreach ($fixtures as $fixture) {
            if (!\is_object($fixture)) {
                $fixture = new $fixture();
            }

            if ($fixture instanceof ContainerAwareInterface) {
                $fixture->setContainer(static::$container);
            }

            $loader->addFixture($fixture);
        }

        $em = static::getEntityManager();
        $purger = new ORMPurger($em);
        $executor = new ORMExecutor($em, $purger);
        $executor->execute($loader->getFixtures());
    }

    public function assertResponseOk(?Response $response = null, ?string $message = null, string $type = 'text/html'): void
    {
        $this->failOnResponseStatusCheck($response, 'isOk', $message, $type);
    }

    public function assertResponseRedirect(?Response $response = null, ?string $message = null, string $type = 'text/html'): void
    {
        $this->failOnResponseStatusCheck($response, 'isRedirect', $message, $type);
    }

    public function assertResponseNotFound(?Response $response = null, ?string $message = null, string $type = 'text/html'): void
    {
        $this->failOnResponseStatusCheck($response, 'isNotFound', $message, $type);
    }

    public function assertResponseForbidden(?Response $response = null, ?string $message = null, string $type = 'text/html'): void
    {
        $this->failOnResponseStatusCheck($response, 'isForbidden', $message, $type);
    }

    public function assertResponseCode(int $expectedCode, ?Response $response = null, ?string $message = null, string $type = 'text/html'): void
    {
        $this->failOnResponseStatusCheck($response, $expectedCode, $message, $type);
    }
    /**
     * @param Response $response
     * @param string   $type
     *
     * @return string
     */
    public function guessErrorMessageFromResponse(Response $response, string $type = 'text/html'): string
    {
        try {
            $crawler = new Crawler();
            $crawler->addContent($response->getContent(), $type);

            if (!\count($crawler->filter('title'))) {
                $add = '';
                $content = $response->getContent();

                if ('Application/json' === $response->headers->get('Content-Type')) {
                    $data = json_decode($content);
                    if ($data) {
                        $content = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                        $add = ' FORMATTED';
                    }
                }
                $title = '[' . $response->getStatusCode() . ']' . $add .' - ' . $content;
            } else {
                $title = $crawler->filter('title')->text();
            }
        } catch (\Exception $e) {
            $title = $e->getMessage();
        }

        return trim($title);
    }

    private function failOnResponseStatusCheck(
        Response $response = null,
        $func = null,
        ?string $message = null,
        string $type = 'text/html'
    ): void
    {
        if (null === $func) {
            $func = 'isOk';
        }

        if (null === $response && self::$client) {
            $response = self::$client->getResponse();
        }

        try {
            if (\is_int($func)) {
                self::assertEquals($func, $response->getStatusCode());
            } else {
                self::assertTrue($response->{$func}());
            }

            return;
        } catch (\Exception $e) {
            // nothing to do
        }

        $err = $this->guessErrorMessageFromResponse($response, $type);
        if ($message) {
            $message = rtrim($message, '.') . ". ";
        }

        if (is_int($func)) {
            $template = "Failed asserting Response status code %s equals %s.";
        } else {
            $template = "Failed asserting that Response[%s] %s.";
            $func = preg_replace('#([a-z])([A-Z])#', '$1 $2', $func);
        }

        $message .= sprintf($template, $response->getStatusCode(), $func, $err);

        $max_length = 100;
        if (mb_strlen($err, 'utf-8') < $max_length) {
            $message .= " " . $this->makeErrorOneLine($err);
        } else {
            $message .= " " . $this->makeErrorOneLine(mb_substr($err, 0, $max_length, 'utf-8') . '...');
            $message .= "\n\n" . $err;
        }

        self::fail($message);
    }

    private function makeErrorOneLine($text)
    {
        return preg_replace('#[\n\r]+#', ' ', $text);
    }

    // Функция для замены сервиса билинга на Mock версию.
    private function getBillingClient(): void
    {
        // запрещаем перезагрузку ядра, чтобы не сбросилась подмена сервиса при запросе
        self::getClient()->disableReboot();

        self::getClient()->getContainer()->set(
            BillingClient::class,
            new BillingClientMock()
        );
    }

    // Функция для проверки авторизации.
    protected function auth(string $data, string $redirectPath)
    {
        // Получаем информацию из запроса
        $requestData = json_decode($data, true);

        // Заменяем сервис
        $this->getBillingClient();
        $client = self::getClient();

        // Переходим на страницу авторизации
        $crawler = $client->request('GET', '/login');
        $this->assertResponseOk();

        // Заполняем форму
        $form = $crawler->selectButton('Вход')->form();
        $form['email'] = $requestData['username'];
        $form['password'] = $requestData['password'];
        $client->submit($form);

        // Проверяем ошибки
        $error = $crawler->filter('#errors');
        self::assertCount(0, $error);

        // Редирект на страницу со списком курсов
        $crawler = $client->followRedirect();
        $this->assertResponseCode(Response::HTTP_OK, $client->getResponse());
        self::assertEquals($redirectPath, $client->getRequest()->getPathInfo());
        return $crawler;
    }
}