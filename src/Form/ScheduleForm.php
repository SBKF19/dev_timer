<?php

namespace App\Form;

use App\Entity\Schedule;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Validator\Constraints\NotBlank;

class ScheduleForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $inputBaseClass = 'border border-[#B4B4B4] rounded-lg w-full h-12 bg-white focus:border-[#2A7DE3] focus:outline-none text-[#8D8D8D] font-light px-4 transition-all';
        $labelBaseClass = 'text-[#1F384C] font-medium mb-2 block text-base';

        $builder
            ->add('dayOfWeek', ChoiceType::class, [
                'label' => 'Jour - champ obligatoire',
                'choices'  => [
                    'Lundi' => 1, 'Mardi' => 2, 'Mercredi' => 3, 'Jeudi' => 4, 'Vendredi' => 5
                ],
                'placeholder' => 'Sélectionnez un jour',
                'attr' => ['class' => 'select ' . $inputBaseClass],
                'label_attr' => ['class' => $labelBaseClass],
                'constraints' => [
                    new NotBlank(message: 'Le jour de la semaine est obligatoire.'),
                ]
            ])
            ->add('startTime', TimeType::class, [
                'label' => 'Heure de début - champ obligatoire',
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'input pl-12 ' . $inputBaseClass,
                    'placeholder' => 'Sélectionnez l\'heure de début',
                    'list' => 'time-suggestions',
                    'step' => 900,
                    'onfocus' => 'this.showPicker()'
                ],
                'label_attr' => ['class' => $labelBaseClass],
                'constraints' => [
                    new NotBlank(message: 'L\'heure de début est obligatoire.'),
                ]
            ])
            ->add('endTime', TimeType::class, [
                'label' => 'Heure de fin - champ obligatoire',
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'input pl-12 ' . $inputBaseClass,
                    'placeholder' => 'Sélectionnez l\'heure de fin',
                    'list' => 'time-suggestions',
                    'step' => 900,
                    'onfocus' => 'this.showPicker()'

                ],
                'label_attr' => ['class' => $labelBaseClass],
                'constraints' => [
                    new NotBlank(message: 'L\'heure de fin est obligatoire.'),
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Schedule::class,
        ]);
    }
}
