<?php

namespace App\Controller;

use App\Exception\BillingAuthException;
use App\Exception\BillingUnavailableException;
use App\Service\BillingClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/private_office")
 */
class PrivateOfficeController extends AbstractController
{
    /**
     * @Route("/", name="private_office")
     * @throws \Exception
     */
    public function index(BillingClient $billingClient): Response
    {
        $this->denyAccessUnlessGranted(
            'ROLE_USER',
            $this->getUser(),
            'У вас нет доступа к этой странице'
        );

        try {
            $response = $billingClient->getCurrentUser($this->getUser());
        } catch (BillingAuthException | BillingUnavailableException $e) {
            throw new \Exception($e->getMessage());
        }

        return $this->render('private_office/index.html.twig', [
            'controller_name' => 'PrivateOfficeController',
            'username' => $response['username'],
            'balance' => $response['balance']
        ]);
    }
}
