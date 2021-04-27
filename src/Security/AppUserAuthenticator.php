<?php

namespace App\Security;

use App\Exception\BillingAuthException;
use App\Exception\BillingUnavailableException;
use App\Service\BillingClient;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Guard\Authenticator\AbstractFormLoginAuthenticator;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Symfony\Component\Serializer\SerializerInterface;

class AppUserAuthenticator extends AbstractFormLoginAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_login';

    private $urlGenerator;
    private $csrfTokenManager;
    private $billingClient;
    private $serializer;
    private $decodingJwt;

    public function __construct(
        UrlGeneratorInterface $urlGenerator,
        CsrfTokenManagerInterface $csrfTokenManager,
        BillingClient $billingClient,
        SerializerInterface $serializer
    ) {
        $this->urlGenerator = $urlGenerator;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->billingClient = $billingClient;
        $this->serializer = $serializer;
    }

    public function supports(Request $request): bool
    {
        return self::LOGIN_ROUTE === $request->attributes->get('_route')
            && $request->isMethod('POST');
    }

    public function getCredentials(Request $request): array
    {
        $credentials = [
            'email' => $request->request->get('email'),
            'password' => $request->request->get('password'),
            'csrf_token' => $request->request->get('_csrf_token'),
        ];
        $request->getSession()->set(
            Security::LAST_USERNAME,
            $credentials['email']
        );

        return $credentials;
    }

    public function getUser($credentials, UserProviderInterface $userProvider): User
    {
        $token = new CsrfToken('authenticate', $credentials['csrf_token']);
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            throw new InvalidCsrfTokenException();
        }

        // Формируем данные для запроса в сервис оплаты
        $data = [
            'username' => $credentials['email'],
            'password' => $credentials['password']
        ];

        $request = $this->serializer->serialize($data, 'json');

        // Запрос к сервису оплаты для получения токена авторизации
        try {
            $dataResponse = $this->billingClient->auth($request);
        } catch (BillingUnavailableException | BillingAuthException $e) {
            throw new CustomUserMessageAuthenticationException($e->getMessage());
        }

        // Проверка ответа и формирование пользователя
        if ($dataResponse) {
            $tokenParts = explode('.', $dataResponse['token']);
            $payload = json_decode(base64_decode($tokenParts[1]), true);

            $user = new User();
            $user->setApiToken($dataResponse['token']);
            $user->setEmail($payload['username']);
            $user->setRoles($payload['roles']);
            $user->setRefreshToken($dataResponse['refresh_token']);
            return $user;
        }

        throw new CustomUserMessageAuthenticationException('Пользователь не найден.');
    }

    public function checkCredentials($credentials, UserInterface $user): bool
    {
        return true;
    }

    public function onAuthenticationSuccess(
        Request $request,
        TokenInterface $token,
        string $providerKey
    ): RedirectResponse {
        if ($targetPath = $this->getTargetPath($request->getSession(), $providerKey)) {
            return new RedirectResponse($targetPath);
        }

        return new RedirectResponse($this->urlGenerator->generate('courses_index'));
    }

    protected function getLoginUrl(): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}
