<?php

namespace App\Controller;

use App\Exception\BillingAuthException;
use App\Exception\BillingUnavailableException;
use App\Form\RegisterType;
use App\Security\AppUserAuthenticator;
use App\Security\User;
use App\Service\BillingClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Guard\GuardAuthenticatorHandler;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Serializer\SerializerInterface;

class SecurityController extends AbstractController
{
    /**
     * @Route("/login", name="app_login")
     * @param AuthenticationUtils $authenticationUtils
     * @return Response
     */
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('courses_index');
        }

        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', ['last_username' => $lastUsername, 'error' => $error]);
    }

    /**
     * @Route("/register", name="app_register", methods={"GET","POST"})
     * @param Request $request
     * @param SerializerInterface $serializer
     * @param BillingClient $billingClient
     * @param \Symfony\Component\Security\Guard\GuardAuthenticatorHandler $guardAuthenticatorHandler
     * @param \App\Security\AppUserAuthenticator $appUserAuthenticator
     * @return Response
     */
    public function register(
        Request $request,
        SerializerInterface $serializer,
        BillingClient $billingClient,
        GuardAuthenticatorHandler $guardAuthenticatorHandler,
        AppUserAuthenticator $appUserAuthenticator
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('private_office');
        }

        $error = null;
        $form = $this->createForm(RegisterType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Создаем пользователя
                $user = new User();
                $user->setEmail($form->getData()['email']);
                $user->setPassword($form->getData()['password']);

                // Формируем данные для запроса
                $data = $serializer->serialize($form->getData(), 'json');
                // Запрос к сервису оплаты для регистрации пользователя
                $billingClient->register($data);
                $guardAuthenticatorHandler->authenticateUserAndHandleSuccess(
                    $user,
                    $request,
                    $appUserAuthenticator,
                    'main'
                );
                return $this->redirectToRoute('courses_index');
            } catch (BillingUnavailableException | BillingAuthException $e) {
                $error = $e->getMessage();
            }
        }

        return $this->render('security/register.html.twig', [
            'form' => $form->createView(),
            'error' => $error
        ]);
    }

    /**
     * @Route("/logout", name="app_logout")
     * @throws \Exception
     */
    public function logout(): void
    {
        throw new \Exception('Don\'t forget to activate logout in security.yaml');
    }
}
