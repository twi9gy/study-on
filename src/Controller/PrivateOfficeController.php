<?php

namespace App\Controller;

use App\Exception\BillingAuthException;
use App\Exception\BillingUnavailableException;
use App\Repository\CourseRepository;
use App\Service\BillingClient;
use App\Service\DecodeJwt;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @Route("/private_office")
 */
class PrivateOfficeController extends AbstractController
{
    /**
     * @Route("/", name="private_office")
     * @throws \Exception
     */
    public function index(BillingClient $billingClient, DecodeJwt $decodeJwt, SerializerInterface $serializer): Response
    {
        $this->denyAccessUnlessGranted(
            'ROLE_USER',
            $this->getUser(),
            'У вас нет доступа к этой странице'
        );

        try {
            $response = $billingClient->getCurrentUser($this->getUser(), $decodeJwt, $serializer);
        } catch (BillingAuthException | BillingUnavailableException $e) {
            throw new \Exception($e->getMessage());
        }

        return $this->render('private_office/index.html.twig', [
            'controller_name' => 'PrivateOfficeController',
            'username' => $response['username'],
            'balance' => $response['balance']
        ]);
    }

    /**
     * @Route("/transaction_history", name="transaction_history")
     * @throws \Exception
     */
    public function transactionHistory(BillingClient $client, CourseRepository $courseRepository): Response
    {
        $this->denyAccessUnlessGranted(
            'ROLE_USER',
            $this->getUser(),
            'У вас нет доступа к этой странице'
        );

        // Получаем транзакции пользователя
        try {
            $transactions = $client->getTransactions($this->getUser());
        } catch (BillingAuthException | BillingUnavailableException $e) {
            throw new \Exception($e->getMessage());
        }

        // Получаем все курсы
        $courses = $courseRepository->findAll();

        $transactionsData = [];
        foreach ($transactions as $transaction) {
            $in_result = false;
            if (isset($transaction['course_code'])) {
                foreach ($courses as $course) {
                    if ($course->getCode() === $transaction['course_code']) {
                        $transactionsData[] = [
                            'type' => $transaction['type'],
                            'amount' => $transaction['amount'],
                            'created_at' => $transaction['created_at'],
                            'course' => [
                                'id' => $course->getId(),
                                'code' => $course->getCode(),
                                'title' => $course->getTitle()
                            ]
                        ];
                        $in_result = true;
                        break;
                    }
                }
            }
            if (!$in_result) {
                $transactionsData[] = [
                    'type' => $transaction['type'],
                    'amount' => $transaction['amount'],
                    'created_at' => $transaction['created_at']
                ];
            }
        }

        return $this->render('private_office/transaction_history.html.twig', [
            'controller_name' => 'PrivateOfficeController',
            'transactions' => $transactionsData
        ]);
    }
}
