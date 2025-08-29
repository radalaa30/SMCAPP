<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SuiviPreparationSearchType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('codeClient', TextType::class, [
                'required' => false,
                'label' => 'Code Client',
                'attr' => ['placeholder' => 'Rechercher par code client']
            ])
            ->add('client', TextType::class, [
                'required' => false,
                'label' => 'Client',
                'attr' => ['placeholder' => 'Rechercher par nom client']
            ])
            ->add('preparateur', TextType::class, [
                'required' => false,
                'label' => 'Préparateur',
                'attr' => ['placeholder' => 'Rechercher par préparateur']
            ])
            ->add('updatedAt', DateType::class, [
                'required' => false,
                'label' => 'Date de mise à jour',
                'widget' => 'single_text',
                'html5' => true,
                'format' => 'yyyy-MM-dd',
                'input' => 'datetime'
            ])
            ->add('dateliv', DateType::class, [
                'required' => false,
                'label' => 'Date de livraison',
                'widget' => 'single_text',
                'html5' => true,
                'format' => 'yyyy-MM-dd',
                'input' => 'datetime'
            ])
            ->add('noBl', TextType::class, [
                'required' => false,
                'label' => 'Numéro BL',
                'attr' => ['placeholder' => 'Rechercher par n° BL']
            ])
            ->add('noCmd', TextType::class, [
                'required' => false,
                'label' => 'Numéro Commande',
                'attr' => ['placeholder' => 'Rechercher par n° commande']
            ])
            ->add('codeProduit', TextType::class, [
                'required' => false,
                'label' => 'Code Produit',
                'attr' => [
                    'placeholder' => 'Rechercher par codes produit (séparés par des virgules)',
                    'data-help' => 'Vous pouvez entrer plusieurs codes séparés par des virgules'
                ]
            ])
            ->add('adresse', TextType::class, [
                'required' => false,
                'label' => 'Adresse',
                'attr' => [
                    'placeholder' => 'Rechercher par adresse',
                    'data-help' => 'Vous pouvez rechercher par adresse ou zone'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'method' => 'GET',
            'csrf_protection' => false,
        ]);
    }
}