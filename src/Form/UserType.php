<?php

namespace App\Form;

use App\Entity\Project;
use App\Entity\Role;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', TextType::class, [
                'label' => 'Email',
            ])
            ->add('firstname', TextType::class, [
                'label' => 'Nom',
            ])
            ->add('lastname', TextType::class, [
                'label' => 'Prénom',
            ])
            ->add('password', PasswordType::class, [
                'label' => 'Mot de passe temporaire',
            ])
            ->add('hired_date', DateTimeType::class, [
                'label' => 'Date d\'embauche',
            ])
            ->add('photo', TextType::class, [
                'label' => 'Photo',
            ])
            ->add('status')
            ->add('contract_end_date', DateTimeType::class, [
                'label' => 'Date de fin de contrat',
            ])
            ->add('color', ColorType::class, [
                'label' => 'couleur associée',
            ])
            ->add('role', EntityType::class, [
                'label' => 'Rôle',
                'class' => Role::class,
                'choice_label' => 'label',
                'placeholder' => 'Choisissez un rôle',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
