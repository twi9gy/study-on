<?php


namespace App\Form;

use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class RegisterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add(
                'email',
                TextType::class,
                [
                    'label' => 'Email',
                    'required' => true,
                    'attr' => ['placeholder' => 'Введите Email.'],
                    'empty_data' => '',
                    'constraints' => [
                        new NotBlank([
                            'message' => 'Введите Email.',
                        ]),
                        new Email([
                            'message' => 'Неверно указан Email.'
                        ])
                    ],
                ]
            )
            ->add(
                'password',
                RepeatedType::class,
                [
                    'label' => 'Пароль',
                    'required' => true,
                    'type' => PasswordType::class,
                    'empty_data' => '',
                    'constraints' => [
                        new NotBlank([
                            'message' => 'Введите пароль.',
                        ]),
                        new Length([
                            'min' => 6,
                            'minMessage' => 'Ваш пароль должен состоять из {{ limit }} символов.',
                        ]),
                    ],
                    'first_options'  => [
                        'label' => 'Пароль',
                        'attr' => [
                            'class' => 'form-control',
                            'placeholder' => 'Введите пароль.'
                        ]
                    ],
                    'second_options' => [
                        'label' => 'Повторный пароль',
                        'attr' => [
                            'class' => 'form-control',
                            'placeholder' => 'Введите повторный пароль.'
                        ]
                    ],
                    'invalid_message' => 'Пароли не совпадают.',
                ]
            )
        ;
    }
}
