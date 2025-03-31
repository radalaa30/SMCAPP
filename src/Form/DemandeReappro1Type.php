<?php

namespace App\Form;

use App\Entity\DemandeReappro;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DemandeReappro1Type extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('idReappro')
           // ->add('idPreparateur')
           // ->add('idCariste')
           // ->add('SonPicking')
            ->add('Adresse')
            ->add('Statut')
            ->add('CreateAt', null, [
                'widget' => 'single_text',
            ])
            ->add('UsernamePrep')
            ->add('UsernameCariste')
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => DemandeReappro::class,
        ]);
    }
}
