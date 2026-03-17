<?php
namespace App\Form;

use App\Entity\Activities;
use App\Entity\HourEntry;
use App\Entity\Project;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class HourEntryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('startDate', TimeType::class, [
                'widget' => 'single_text',
                'label' => 'Début',
                'input' => 'datetime',
            ])
            ->add('endDate', TimeType::class, [
                'widget' => 'single_text',
                'label' => 'Fin',
                'input' => 'datetime',
            ])
            ->add('activity', EntityType::class, [
                'class' => Activities::class,
                'choice_label' => 'label',
                'placeholder' => 'Choisir une activité',
                'choice_attr' => function(Activities $activity) {
                    return ['data-need-project' => $activity->isNeedProject() ? '1' : '0'];
                },
            ])
            ->add('project', EntityType::class, [
                'class' => Project::class,
                'choice_label' => 'name',
                'placeholder' => 'Choisir un projet',
                'required' => false,
            ])
            ->add('commentary', TextareaType::class, [
                'label' => 'Commentaire',
                'required' => false,
                'attr' => ['rows' => 3]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => HourEntry::class,
            'constraints' => [
                // Utilisation d'une fonction anonyme pour éviter les problèmes de référence circulaire
                new Callback(function (HourEntry $hourEntry, ExecutionContextInterface $context) {
                    $activity = $hourEntry->getActivity();
                    $project = $hourEntry->getProject();

                    // 1. Vérification du projet obligatoire
                    if ($activity && $activity->isNeedProject() && !$project) {
                        $context->buildViolation('Cette activité nécessite de sélectionner un projet.')
                            ->atPath('project')
                            ->addViolation();
                    }

                    // 2. Vérification de la cohérence des heures
                    if ($hourEntry->getStartDate() && $hourEntry->getEndDate()) {
                        if ($hourEntry->getStartDate() >= $hourEntry->getEndDate()) {
                            $context->buildViolation('L\'heure de fin doit être strictement après l\'heure de début.')
                                ->atPath('endDate')
                                ->addViolation();
                        }
                    }
                }),
            ],
        ]);
    }

    
}