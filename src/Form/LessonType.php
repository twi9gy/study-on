<?php

namespace App\Form;

use App\Entity\Course;
use App\Entity\Lesson;
use App\Repository\CourseRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LessonType extends AbstractType
{
    private $courseRepository;

    public function __construct(CourseRepository $courseRepository)
    {
        $this->courseRepository = $courseRepository;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Получаем Id курса
        $selectedCourse = $options['selected_course'];

        $builder
            ->add('title', TextType::class, [
                'label' => 'Название урока',
                'required' => true
            ])
            ->add('content', TextareaType::class, [
                'label' => 'Содержимое',
                'required' => true
            ])
            ->add('number', NumberType::class, ['label' => 'Порядкой номер урока в курсе'])
            ->add('course', HiddenType::class, ['data' => $selectedCourse, 'data_class' => null]);
        ;

        // Data Transformer для поля course
        $builder->get("course")->addModelTransformer(new CallbackTransformer(
            // Transformer
            function ($courseId) {
                return (string) $courseId;
            },
            // ReverseTransformer
            function ($courseId) {
                return $this->courseRepository->find($courseId);
            }
        ));
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Lesson::class,
            'selected_course' => 0
        ]);
    }
}
