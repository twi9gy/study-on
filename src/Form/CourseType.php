<?php

namespace App\Form;

use App\Entity\Course;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CourseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('code', TextType::class, [
                'label' => 'Код курса',
                'required' => true,
                'empty_data' => '',
            ])
            ->add('title', TextType::class, [
                'label' => 'Название курса',
                'required' => true,
                'empty_data' => '',
            ])
            ->add('description', TextareaType::class, ['label' => 'Описание курса'])
            ->add('cost', MoneyType::class, [
                'mapped' => false,
                'label' => 'Стоимость курса',
                'currency' => 'rub'
            ])
            ->add('type', ChoiceType::class, [
                'mapped' => false,
                'label' => 'Тип курса',
                'choices' => [
                    'Арендный' => 'rent',
                    'Покупной' => 'buy',
                    'Бесплатный' => 'free'
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Course::class,
        ]);
    }
}
