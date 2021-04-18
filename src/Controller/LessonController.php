<?php

namespace App\Controller;

use App\Entity\Lesson;
use App\Form\LessonType;
use App\Repository\CourseRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/lessons")
 */
class LessonController extends AbstractController
{
    /**
     * @Route("/new", name="lesson_new", methods={"GET","POST"})
     * @param Request $request
     * @param \App\Repository\CourseRepository $courseRepository
     * @return Response
     */
    public function new(Request $request, CourseRepository $courseRepository): Response
    {
        $this->denyAccessUnlessGranted(
            'ROLE_SUPER_ADMIN',
            $this->getUser(),
            'У вас нет доступа к этой странице'
        );

        // Получаем Id курса
        $courseId = $request->get('course_id');
        // Получаем курс для перехода обратно
        $course = $courseRepository->find($courseId);


        $lesson = new Lesson();
        // В options передаем Id курса
        $form = $this->createForm(LessonType::class, $lesson, ['selected_course' => $courseId]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($lesson);
            $entityManager->flush();

            return $this->redirectToRoute('course_show', ['id' => $lesson->getCourse()->getId()]);
        }

        return $this->render('lesson/new.html.twig', [
            'lesson' => $lesson,
            'course' => $course,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="lesson_show", methods={"GET"})
     * @param Lesson $lesson
     * @return Response
     */
    public function show(Lesson $lesson): Response
    {
        $this->denyAccessUnlessGranted(
            'ROLE_USER',
            $this->getUser(),
            'У вас нет доступа к этой странице'
        );

        return $this->render('lesson/show.html.twig', [
            'lesson' => $lesson,
        ]);
    }

    /**
     * @Route("/{id}/edit", name="lesson_edit", methods={"GET","POST"})
     * @param Request $request
     * @param Lesson $lesson
     * @return Response
     */
    public function edit(Request $request, Lesson $lesson): Response
    {
        $this->denyAccessUnlessGranted(
            'ROLE_SUPER_ADMIN',
            $this->getUser(),
            'У вас нет доступа к этой странице'
        );

        $form = $this->createForm(LessonType::class, $lesson, ['selected_course' => $lesson->getCourse()->getId()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('course_show', ['id' => $lesson->getCourse()->getId()]);
        }

        return $this->render('lesson/edit.html.twig', [
            'lesson' => $lesson,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="lesson_delete", methods={"DELETE"})
     * @param Request $request
     * @param Lesson $lesson
     * @return Response
     */
    public function delete(Request $request, Lesson $lesson): Response
    {
        $this->denyAccessUnlessGranted(
            'ROLE_SUPER_ADMIN',
            $this->getUser(),
            'У вас нет доступа к этой странице'
        );

        if ($this->isCsrfTokenValid('delete'.$lesson->getId(), $request->request->get('_token'))) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($lesson);
            $entityManager->flush();
        }

        return $this->redirectToRoute('course_show', ['id' => $lesson->getCourse()->getId()]);
    }
}
