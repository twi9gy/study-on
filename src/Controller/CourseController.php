<?php

namespace App\Controller;

use App\Entity\Course;
use App\Exception\BillingAuthException;
use App\Exception\BillingUnavailableException;
use App\Form\CourseType;
use App\Form\PaymentType;
use App\Repository\CourseRepository;
use App\Repository\LessonRepository;
use App\Service\BillingClient;
use App\Service\DecodeJwt;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @Route("/courses")
 */
class CourseController extends AbstractController
{
    /**
     * @Route("/", name="courses_index", methods={"GET"})
     * @param CourseRepository $courseRepository
     * @param BillingClient $client
     * @return Response
     * @throws BillingAuthException
     */
    public function index(CourseRepository $courseRepository, BillingClient $client): Response
    {
        $this->denyAccessUnlessGranted(
            'ROLE_USER',
            $this->getUser(),
            'У вас нет доступа к этой странице'
        );

        // Получаем все курсы из биллинга
        try {
            $coursesBilling = $client->getCourses($this->getUser());
        } catch (BillingUnavailableException $e) {
            throw new BillingAuthException($e->getMessage());
        }

        // Получение курсов пользователя
        try {
            $coursesUser = $client->getUserCourses($this->getUser());
        } catch (BillingAuthException | BillingUnavailableException $e) {
            throw new BillingAuthException($e->getMessage());
        }

        // Получаем все курсы study-on
        $courses = $courseRepository->findAll();

        // Формируем ответ
        $coursesData = [];
        foreach ($coursesBilling as $courseBilling) {
            // Ищем курс, который вернулся с сервиса оплаты, в репозитории
            $course = $courseRepository->findOneBy(['code' => $courseBilling['code']]);
            if ($course) {
                // Если курс бесплатный
                if ($courseBilling['type'] === 'free') {
                    $coursesData[] = $this->createCourseRecord(
                        $course->getId(),
                        $course->getCode(),
                        $course->getTitle(),
                        $course->getDescription(),
                        $courseBilling['type'],
                        null,
                        null,
                        null
                    );
                } else {
                    // Иначе опрелеляем время окончания аренды, если он арендуемый.
                    // Если он платный, то определяем оплачен ли данный курс пользователем
                    $expires_at = null;
                    $purchased = false;
                    foreach ($coursesUser as $courseUser) {
                        if ($course->getCode() === $courseUser['code'] && $courseBilling['type'] === 'rent') {
                            $expires_at = $courseUser['expires_at'];
                            break;
                        }

                        if ($course->getCode() === $courseUser['code'] && $courseBilling['type'] === 'buy') {
                            $purchased = true;
                            break;
                        }
                    }
                    if ($courseBilling['type'] === 'rent') {
                        $coursesData[] = $this->createCourseRecord(
                            $course->getId(),
                            $course->getCode(),
                            $course->getTitle(),
                            $course->getDescription(),
                            $courseBilling['type'],
                            $courseBilling['cost'],
                            $purchased,
                            $expires_at
                        );
                    } elseif ($courseBilling['type'] === 'buy') {
                        $coursesData[] = $this->createCourseRecord(
                            $course->getId(),
                            $course->getCode(),
                            $course->getTitle(),
                            $course->getDescription(),
                            $courseBilling['type'],
                            $courseBilling['cost'],
                            $purchased,
                            null
                        );
                    }
                }
            }
        }

        // Если количество курсов в study-on и billing.study-on разные
        if (count($coursesData) !== count($courses)) {
            foreach ($courses as $course) {
                $iter = 0;
                foreach ($coursesData as $courseData) {
                    if ($course->getCode() !== $courseData['code']) {
                        ++$iter;
                    }
                }
                // Если курс не совпадает ни с одним из найденных курсов,
                // то он был создан недавно и его еще нет в billing
                if ($iter === count($coursesData)) {
                    $coursesData[] = $this->createCourseRecord(
                        $course->getId(),
                        $course->getCode(),
                        $course->getTitle(),
                        $course->getDescription(),
                        null,
                        null,
                        null,
                        null
                    );
                }
            }
        }

        if (count($coursesData) > 0) {
            return $this->render('course/index.html.twig', [
                'courses' => $coursesData,
            ]);
        }

        return $this->render('course/index.html.twig', [
            'courses' => null,
        ]);
    }

    private function createCourseRecord(
        int $id,
        string $code,
        string $title,
        string $description,
        ?string $type,
        ?float $price,
        ?bool $purchased,
        ?string $expires_at
    ): array {
        return [
            'id' => $id,
            'code' => $code,
            'title' => $title,
            'description' => $description,
            'type' => $type,
            'price' => $price,
            'purchased' => $purchased,
            'expires_at' =>$expires_at
        ];
    }

    /**
     * @Route("/new", name="course_new", methods={"GET","POST"})
     * @param Request $request
     * @param BillingClient $client
     * @param SerializerInterface $serializer
     * @return Response
     * @throws BillingUnavailableException
     */
    public function new(
        Request $request,
        BillingClient $client,
        SerializerInterface $serializer
    ): Response {
        $this->denyAccessUnlessGranted(
            'ROLE_SUPER_ADMIN',
            $this->getUser(),
            'У вас нет доступа к этой странице'
        );

        $course = new Course();
        $form = $this->createForm(CourseType::class, $course);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Сохранение курса
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($course);
            $entityManager->flush();

            // Запрос в сервис billing для создания курса
            $dataRequest = [
                'type' => $form->get('type')->getNormData(),
                'title' => $form->get('title')->getNormData(),
                'code' => $form->get('code')->getNormData(),
                'price' => $form->get('cost')->getNormData()
            ];
            $client->createCourse($this->getUser(), $serializer->serialize($dataRequest, 'json'));

            return $this->redirectToRoute('courses_index');
        }

        return $this->render('course/new.html.twig', [
            'course' => $course,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="course_show", methods={"GET", "POST"})
     * @param Course $course
     * @param LessonRepository $lessonRepository
     * @param BillingClient $client
     * @param DecodeJwt $decodeJwt
     * @param SerializerInterface $serializer
     * @return Response
     * @throws BillingAuthException
     */
    public function show(
        Request $request,
        Course $course,
        LessonRepository $lessonRepository,
        BillingClient $client,
        DecodeJwt $decodeJwt,
        SerializerInterface $serializer
    ): Response {
        $this->denyAccessUnlessGranted(
            'ROLE_USER',
            $this->getUser(),
            'У вас нет доступа к этой странице'
        );

        // Получение информации о курсе из сервиса billing
        try {
            $courseData = $client->getCourse($this->getUser(), $course);
        } catch (BillingAuthException | BillingUnavailableException $e) {
            throw new BillingAuthException($e->getMessage());
        }

        // Если курса нет в billing.study-on
        if (isset($courseData['code']) && $courseData['code'] === 500) {
            $lessons = $lessonRepository->findByCourse($course);

            return $this->render('course/show.html.twig', [
                'course' => $course,
                'lessons' => $lessons,
                'action' => null,
                'balance' => 0
            ]);
        }

        // Получение курсов пользователя
        try {
            $coursesUser = $client->getUserCourses($this->getUser());
        } catch (BillingAuthException | BillingUnavailableException $e) {
            throw new BillingAuthException($e->getMessage());
        }

        // Получение информации о пользователи, для взаимодействия с оплатой
        // Эта информация нужна для определения доступности кнопки оплаты курса
        try {
            $userData = $client->getCurrentUser($this->getUser(), $decodeJwt, $serializer);
        } catch (BillingAuthException | BillingUnavailableException $e) {
            throw new \Exception($e->getMessage());
        }

        $purchased = false;
        $rented = false;

        // Определяем куплен ли курс у пользователя
        foreach ($coursesUser as $courseUser) {
            if ($courseUser['code'] === $course->getCode()) {
                if ($courseData['type'] === 'rent') {
                    $rented = true;
                } elseif ($courseData['type'] === 'buy') {
                    $purchased = true;
                }
            }
        }

        $lessons = $lessonRepository->findByCourse($course);
        $action = null;

        if (!$purchased && $courseData['type'] === 'buy') {
            $courseInfo = [
                'id' => $course->getId(),
                'code' => $course->getCode(),
                'title' => $course->getTitle(),
                'description' => $course->getDescription(),
                'price' => $courseData['price']
            ];
            $course = $courseInfo;
            $action = 'Купить';
        } elseif (!$rented && $courseData['type'] === 'rent') {
            $courseInfo = [
                'id' => $course->getId(),
                'code' => $course->getCode(),
                'title' => $course->getTitle(),
                'description' => $course->getDescription(),
                'price' => $courseData['price']
            ];
            $course = $courseInfo;
            $action = 'Арендовать';
        }

        // Форма для оплаты курса
        $form = $this->createForm(PaymentType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            return $this->redirectToRoute('course_pay', ['id' => $course['id']]);
        }

        return $this->render('course/show.html.twig', [
            'course' => $course,
            'lessons' => $lessons,
            'action' => $action,
            'balance' => $userData['balance'],
            'form' => $form->createView()
        ]);
    }

    /**
     * @Route("/{id}/edit", name="course_edit", methods={"GET","POST"})
     * @param Request $request
     * @param Course $course
     * @param BillingClient $client
     * @param SerializerInterface $serializer
     * @return Response
     * @throws BillingAuthException
     * @throws BillingUnavailableException
     */
    public function edit(
        Request $request,
        Course $course,
        BillingClient $client,
        SerializerInterface $serializer
    ): Response {
        $this->denyAccessUnlessGranted(
            'ROLE_SUPER_ADMIN',
            $this->getUser(),
            'У вас нет доступа к этой странице'
        );

        // Запрос с billing для получения информации о курсе
        $courseData = $client->getCourse($this->getUser(), $course);
        $courseCode = $course->getCode();

        $form = $this->createForm(CourseType::class, $course);
        $form->get('type')->setData($courseData['type']);
        $form->get('cost')->setData($courseData['price'] ?? 0);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $dataRequest = [
                'type' => $form->get('type')->getNormData(),
                'title' => $form->get('title')->getNormData(),
                'code' => $form->get('code')->getNormData(),
                'price' => $form->get('cost')->getNormData()
            ];

            $responseData = $client->editCourse(
                $this->getUser(),
                $courseCode,
                $serializer->serialize($dataRequest, 'json')
            );

            // Если в сервисе billing успешно изменился курс, то сохраняем изменения
            if (isset($responseData['success'])) {
                $this->getDoctrine()->getManager()->flush();
                return $this->redirectToRoute('course_show', ['id' => $course->getId()]);
            }

            return $this->render('course/edit.html.twig', [
                'course' => $course,
                'form' => $form->createView(),
            ]);
        }

        return $this->render('course/edit.html.twig', [
            'course' => $course,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="course_delete", methods={"DELETE"})
     * @param Request $request
     * @param Course $course
     * @return Response
     */
    public function delete(Request $request, Course $course): Response
    {
        $this->denyAccessUnlessGranted(
            'ROLE_SUPER_ADMIN',
            $this->getUser(),
            'У вас нет доступа к этой странице'
        );

        if ($this->isCsrfTokenValid('delete'.$course->getId(), $request->request->get('_token'))) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($course);
            $entityManager->flush();
        }

        return $this->redirectToRoute('courses_index');
    }

    /**
     * @Route("/{id}/pay", name="course_pay", methods={"GET","POST"})
     * @param BillingClient $client
     * @param Course $course
     * @return Response
     * @throws BillingAuthException
     */
    public function paymentCourse(BillingClient $client, Course $course): Response
    {
        $this->denyAccessUnlessGranted(
            'ROLE_USER',
            $this->getUser(),
            'У вас нет доступа к этой странице'
        );

        // Запрос в сервис оплаты курса
        try {
            $responseData = $client->paymentCourse($this->getUser(), $course);
        } catch (BillingUnavailableException $e) {
            throw new BillingAuthException($e->getMessage());
        }

        // Если деньги списались
        if (isset($responseData['success']) && $responseData['success']) {
            $this->addFlash('success', 'Курс успешно оплачен.');
            return $this->redirectToRoute('course_show', ['id' => $course->getId()]);
        }

        $this->addFlash('unsuccessful', $responseData['message']);
        return $this->redirectToRoute('course_show', ['id' => $course->getId()]);
    }
}
