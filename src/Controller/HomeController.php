<?php

namespace App\Controller;

use App\Exception\BillingAuthException;
use App\Exception\BillingUnavailableException;
use App\Repository\CourseRepository;
use App\Service\BillingClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    /**
     * @Route("/", name="home")
     * @param CourseRepository $courseRepository
     * @param BillingClient $client
     * @return Response
     */
    public function index(CourseRepository $courseRepository, BillingClient $client): Response
    {
//        // Получаем все курсы из биллинга
//        try {
//            $coursesBilling = $client->getCourses();
//        } catch (BillingUnavailableException $e) {
//            throw new BillingAuthException($e->getMessage());
//        }
//
//        // Формируем ответ
//        $coursesData = [];
//        foreach ($coursesBilling as $courseBilling) {
//            $course = $courseRepository->findOneBy(['code' => $courseBilling['code']]);
//            if ($courseBilling['type'] === 'free') {
//                $coursesData[] = [
//                    'id' => $course->getId(),
//                    'code' => $course->getCode(),
//                    'title' => $course->getTitle(),
//                    'description' => $course->getDescription(),
//                    'type' => $courseBilling['type'],
//                    'price' => null,
//                ];
//            } else {
//                $coursesData[] = [
//                    'id' => $course->getId(),
//                    'code' => $course->getCode(),
//                    'title' => $course->getTitle(),
//                    'description' => $course->getDescription(),
//                    'type' => $courseBilling['type'],
//                    'price' => $courseBilling['price'],
//                ];
//            }
//        }
//
//        if (count($coursesData) > 0) {
//            return $this->render('home/index.html.twig', [
//                'courses' => $coursesData,
//            ]);
//        }

        if (!$this->getUser()) {
            $courses = $courseRepository->findAll();

            return $this->render('home/index.html.twig', [
                'courses' => $courses,
            ]);
        }

        return $this->redirectToRoute('courses_index');
    }
}
