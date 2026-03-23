<?php

namespace App\Form;

use App\Entity\Project;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use App\Repository\UserRepository;

class ProjectType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom du projet - champ obligatoire',
                'attr' => ['placeholder' => 'Saisissez le nom du projet']
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description courte',
                'required' => false,
                'attr' => ['placeholder' => 'Saisissez une description courte', 'rows' => 1]
            ])
            ->add('created_at', DateTimeType::class, [
                'label' => 'Date de création du projet - champ obligatoire',
                'input' => 'datetime_immutable',
            ])
            ->add('archived_at', CheckboxType::class, [
                'label' => 'actif',
                'required' => false,
                'mapped' => false,
                'data' => true,
                'attr' => ['class' => 'form-check-input']
            ])
            ->add('color', ColorType::class, [
                'label' => 'Couleur du projet',
            ])
            ->add('manager', EntityType::class, [
                'class' => User::class,
                'label' => 'Responsable de projet - champ obligatoire',
                'choice_label' => 'lastname',
                'placeholder' => 'Sélectionnez un responsable de projet',
                'query_builder' => function (UserRepository $er) {
                    return $er->createQueryBuilder('u')
                        ->join('u.role', 'r') // On joint la table/entité Role
                        ->where('r.label = :roleLabel')
                        ->setParameter('roleLabel', 'Responsable')
                        ->orderBy('u.lastname', 'ASC');
                },
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Project::class,
        ]);
    }
}
