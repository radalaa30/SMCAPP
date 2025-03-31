<?php

namespace App\Form;

use App\Entity\DemandeReappro;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class ValidationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
        ->add('adresse', TextType::class, [
            'attr' => [
                'onpaste' => 'return false;',
                'oncopy' => 'return false;',
                'oncut' => 'return false;',
            ]
        ])
        ->add('save', ButtonType::class, [
            'label' => 'Envoyer',
            'attr' => [
                'class' => 'btn btn-primary',
                'onClick' => 'this.closest("form").submit(); return false;'
            ],
            'label_html' => true,
            'label' => '<a href="#" id="valider" class="text-decoration-none text-white">Valider</a>'
        ])
    ;

        /*
            ->add('idReappro')
            ->add('idPreparateur')
            ->add('idCariste')
            ->add('SonPicking')
            
            ->add('Statut', HiddenType::class, [
                'mapped' => false, // ne sera pas persisté dans l'entité
            ])
            ->add('CreateAt', null, [
                'widget' => 'single_text',
                HiddenType::class, [
                    'mapped' => false, // ne sera pas persisté dans l'entité
                ]
            ])
            ->add('UpdateAt', null, [
                'widget' => 'single_text',
            ])
                */
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => DemandeReappro::class,
        ]);
    }
}
