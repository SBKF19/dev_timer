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
        $user = $options['data'] ?? null;
        $isEdit = $user && $user->getId() !== null;
        
       $builder
            ->add('email', TextType::class, [
                'label' => 'Email',
            ])
            ->add('firstname', TextType::class, [
                'label' => 'Prénom', // Correction : c'était 'Nom' dans ton code
            ])
            ->add('lastname', TextType::class, [
                'label' => 'Nom', // Correction : c'était 'Prénom' dans ton code
            ]);

        // 3. On n'ajoute le champ password que si on n'est PAS en édition
        if (!$isEdit) {
            $builder->add('password', PasswordType::class, [
                'label' => 'Mot de passe temporaire',
                'required' => true,
            ]);
        }

        $builder
            ->add('hired_date', DateTimeType::class, [
                'label' => 'Date d\'embauche',
                'widget' => 'single_text', // Recommandé pour les champs HTML5
            ])
            ->add('photo', TextType::class, [
                'label' => 'Photo',
                'required' => false,
            ])
            ->add('status')
            ->add('contract_end_date', DateTimeType::class, [
                'label' => 'Date de fin de contrat',
                'required' => false,
                'widget' => 'single_text',
            ])
            ->add('color', ColorType::class, [
                'label' => 'Couleur associée',
            ])
            ->add('role', EntityType::class, [
                'label' => 'Rôle',
                'class' => Role::class,
                'choice_label' => 'label',
                'placeholder' => 'Choisissez un rôle',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
