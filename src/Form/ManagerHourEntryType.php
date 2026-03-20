<?php

namespace App\Form;

use App\Entity\Activities;
use App\Entity\HourEntry;
use App\Entity\Project;
use App\Entity\User;
use App\Repository\ScheduleRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class ManagerHourEntryType extends AbstractType
{
    private ScheduleRepository $scheduleRepository;

    public function __construct(ScheduleRepository $scheduleRepository)
    {
        $this->scheduleRepository = $scheduleRepository;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // 1. CHOIX DU DÉVELOPPEUR (Nouveau pour Manager)
            ->add('user', EntityType::class, [
                'class' => User::class,
                'choice_label' => function(User $user) {
                    return strtoupper($user->getLastname()) . ' ' . $user->getFirstname();
                },
                'label' => 'Développeur',
                'placeholder' => 'Sélectionner le développeur',
            ])
            // 2. CHOIX DE LA DATE (Nouveau pour Manager)
            ->add('entryDate', DateType::class, [
                'mapped' => false, // On fusionnera avec startDate/endDate dans le contrôleur
                'widget' => 'single_text',
                'label' => 'Date de la saisie',
                'data' => $options['default_date'],
            ])
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
                'choice_attr' => fn(Activities $a) => ['data-need-project' => $a->isNeedProject() ? '1' : '0'],
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
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $scheduleRepo = $this->scheduleRepository;

        $resolver->setDefaults([
            'data_class' => HourEntry::class,
            'default_date' => new \DateTime(),
            'constraints' => [
                new Callback(function (HourEntry $hourEntry, ExecutionContextInterface $context) use ($scheduleRepo) {
                    $activity = $hourEntry->getActivity();
                    $project = $hourEntry->getProject();

                    // 1. Vérification projet obligatoire
                    if ($activity && $activity->isNeedProject() && !$project) {
                        $context->buildViolation('Cette activité nécessite un projet.')
                            ->atPath('project')->addViolation();
                    }

                    // 2. Cohérence des heures
                    if ($hourEntry->getStartDate() && $hourEntry->getEndDate()) {
                        if ($hourEntry->getStartDate() >= $hourEntry->getEndDate()) {
                            $context->buildViolation('L\'heure de fin doit être après le début.')
                                ->atPath('endDate')->addViolation();
                        }
                    }

                    // 3. Validation Planning dynamique
                    $form = $context->getRoot();
                    $dateObj = $form->get('entryDate')->getData();
                    
                    if (!$dateObj || !$hourEntry->getUser()) return;

                    $dayOfWeek = (int)$dateObj->format('N');
                    // On cherche les horaires du développeur sélectionné dans le form
                    $schedules = $scheduleRepo->findBy([
                        'dayOfWeek' => $dayOfWeek,
                    ]);

                    if (empty($schedules)) {
                        $context->buildViolation("Aucun créneau de travail n'est défini pour ce jour (ex: Samedi/Dimanche).")
                            ->atPath('entryDate')
                            ->addViolation();
                        return;
                    }

                    $userStart = $hourEntry->getStartDate()->format('H:i');
                    $userEnd = $hourEntry->getEndDate()->format('H:i');
                    $isValid = false;

                    foreach ($schedules as $s) {
                        if ($userStart >= $s->getStartTime()->format('H:i') && $userEnd <= $s->getEndTime()->format('H:i')) {
                            $isValid = true;
                            break;
                        }
                    }

                    if (!$isValid) {
                        $context->buildViolation("Hors créneaux de travail du développeur pour ce jour.")
                            ->atPath('endDate')->addViolation();
                    }
                }),
            ],
        ]);
    }
}