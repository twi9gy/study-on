<?php

namespace App\Controller;

use App\Entity\Course;
use App\Form\CourseType;
use App\Repository\CourseRepository;
use App\Repository\LessonRepository;
use PHPUnit\Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/courses")
 */
class CourseController extends AbstractController
{
    /**
         * @Route("/", name="courses_index", methods={"GET"})
     * @param CourseRepository $courseRepository
     * @return Response
     */
    public function index(CourseRepository $courseRepository): Response
    {
        $courses = $courseRepository->findAll();

        if (count($courses) > 0) {
            return $this->render('course/index.html.twig', [
                'courses' => $courses,
            ]);
        }

        return $this->render('course/index.html.twig', [
            'courses' => null,
        ]);
    }

    /**
     * @Route("/new", name="course_new", methods={"GET","POST"})
     * @param Request $request
     * @return Response
     */
    public function new(Request $request): Response
    {
        $course = new Course();
        $form = $this->createForm(CourseType::class, $course);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($course);
            $entityManager->flush();

            return $this->redirectToRoute('courses_index');
        }

        return $this->render('course/new.html.twig', [
            'course' => $course,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="course_show", methods={"GET"})
     * @param Course $course
     * @param LessonRepository $lessonRepository
     * @return Response
     */
    public function show(Course $course, LessonRepository $lessonRepository): Response
    {
        $lessons = $lessonRepository->findByCourse($course);

        return $this->render('course/show.html.twig', [
            'course' => $course,
            'lessons' => $lessons,
        ]);
    }

    /**
     * @Route("/{id}/edit", name="course_edit", methods={"GET","POST"})
     * @param Request $request
     * @param Course $course
     * @return Response
     */
    public function edit(Request $request, Course $course): Response
    {
        $form = $this->createForm(CourseType::class, $course);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('course_show', ['id' => $course->getId()]);
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
        if ($this->isCsrfTokenValid('delete'.$course->getId(), $request->request->get('_token'))) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($course);
            $entityManager->flush();
        }

        return $this->redirectToRoute('courses_index');
    }
}
