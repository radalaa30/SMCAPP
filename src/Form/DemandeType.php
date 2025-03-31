<?php

namespace App\Form;

use App\Entity\Demande;
use PhpParser\Node\Stmt\Label;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DemandeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
        >add('adresse', TextType::class, [
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
            'label' => '<a href="#" class="text-decoration-none text-white">Envoyer</a>'
        ])
    ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Demande::class,
        ]);
    }
}
