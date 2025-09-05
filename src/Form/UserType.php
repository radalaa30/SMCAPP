<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class UserType extends AbstractType
{
    /**
     * Rôles proposés dans le back-office.
     * ROLE_USER est implicite (ajouté par getRoles()),
     * donc on ne l’affiche pas ici pour éviter les doublons.
     */
    private const ROLE_CHOICES = [
        'Administrateur' => 'ROLE_ADMIN',
        'Manager'        => 'ROLE_MANAGER',
        // 'Utilisateur'  => 'ROLE_USER', // inutile, implicite
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = (bool) ($options['is_edit'] ?? false);

        $builder
            ->add('username', TextType::class, [
                'label' => 'Identifiant',
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(min: 3, max: 180),
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
                'label'    => 'Rôles',
                'choices'  => self::ROLE_CHOICES,
                'expanded' => true,   // checkboxes
                'multiple' => true,
                'required' => false,
                'help'     => 'ROLE_USER est automatiquement attribué à tous les comptes.',
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type'            => PasswordType::class,
                'mapped'          => false,
                'required'        => !$isEdit, // obligatoire en création, optionnel en édition
                'invalid_message' => 'Les mots de passe doivent correspondre.',
                'first_options'   => [
                    'label' => $isEdit ? 'Nouveau mot de passe (optionnel)' : 'Mot de passe',
                    'attr'  => ['autocomplete' => 'new-password'],
                ],
                'second_options'  => [
                    'label' => $isEdit ? 'Confirmer le nouveau mot de passe' : 'Confirmer le mot de passe',
                    'attr'  => ['autocomplete' => 'new-password'],
                ],
                'constraints'     => $isEdit ? [] : [
                    new Assert\NotBlank(message: 'Merci de saisir un mot de passe.'),
                    new Assert\Length(min: 6, minMessage: 'Au moins {{ limit }} caractères.'),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'is_edit'    => false, // passé à true dans l’action edit
        ]);
    }
}
