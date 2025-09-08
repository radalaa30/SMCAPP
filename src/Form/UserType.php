<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class UserType extends AbstractType
{
    /** Rôles proposés dans l’UI */
    private const ROLE_CHOICES = [
        'Administrateur'       => 'ROLE_ADMIN',
        'Consultation'         => 'ROLE_CONSULTATION',
        'Cariste'              => 'ROLE_CARISTE',
        'Préparateur'          => 'ROLE_PREP',
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = (bool) ($options['is_edit'] ?? false);

        $builder
            ->add('username', TextType::class, [
                'label' => 'Nom d’utilisateur',
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(min: 2, max: 180),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Email(),
                    new Assert\Length(max: 255),
                ],
            ])
            ->add('roles', ChoiceType::class, [
                'label'   => 'Rôles',
                'choices' => self::ROLE_CHOICES,
                'expanded' => true,
                'multiple' => true,
                'help'     => 'ROLE_USER est attribué automatiquement.',
            ])
            ->add('plainPassword', PasswordType::class, [
                'label' => $isEdit ? 'Nouveau mot de passe (laisser vide pour ne pas changer)' : 'Mot de passe',
                'mapped' => false,
                'required' => !$isEdit,
                'attr' => ['autocomplete' => 'new-password'],
                'constraints' => $isEdit
                    ? []
                    : [new Assert\NotBlank(), new Assert\Length(min: 4, max: 4096)],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'is_edit'    => false,
        ]);
    }
}
