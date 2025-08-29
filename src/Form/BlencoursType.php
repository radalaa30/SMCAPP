<?php

namespace App\Form;

use App\Entity\Blencours;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BlencoursType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('numBl', TextType::class, [
                'label' => 'Numéro de BL',
                'attr' => [
                    'placeholder' => 'Entrez le numéro de BL',
                    'class' => 'form-control',
                    'id' => 'blencours_numBl'
                ]
            ])
            ->add('statut', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'En attente' => 'En attente',
                    'En cours' => 'En cours',
                    'Traité' => 'Traité'
                ],
                'attr' => [
                    'class' => 'form-control',
                    'id' => 'blencours_statut'
                ]
            ])
            ->add('Pickingok', CheckboxType::class, [
                'label' => 'Picking OK',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input',
                    'id' => 'blencours_Pickingok'
                ],
                'label_attr' => [
                    'class' => 'form-check-label'
                ]
            ])
            ->add('Pickingnok', CheckboxType::class, [
                'label' => 'Picking NOK',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input',
                    'id' => 'blencours_Pickingnok'
                ],
                'label_attr' => [
                    'class' => 'form-check-label'
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Blencours::class,
        ]);
    }
}