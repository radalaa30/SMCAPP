<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use App\Entity\DemandeReappro;

class DemandeReapproType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
        ->add('adresse')
        ->add('save', ButtonType::class, [
            'label' => 'Envoyer',
            'attr' => [
                'class' => 'btn btn-primary',
                'onClick' => 'this.closest("form").submit(); return false;'
            ],
            'label_html' => true,
            'label' => '<a href="#" class="text-decoration-none text-white">Envoyer</a>'
        ])
    ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // Configure your form options here
        ]);
    }
}
