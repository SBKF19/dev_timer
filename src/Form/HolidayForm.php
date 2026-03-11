<?php

namespace App\Form;

use App\Entity\Holiday;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\NotBlank;

class HolidayForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $inputBaseClass = 'border border-[#B4B4B4] rounded-lg w-full h-12 bg-white focus:border-[#2A7DE3] focus:outline-none text-[#8D8D8D] font-light px-4 transition-all';
        $labelBaseClass = 'text-[#1F384C] font-medium mb-2 block text-base';

        $builder
            ->add('date', DateType::class, [
                'label' => 'Date - champ obligatoire',
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'input pl-12 ' . $inputBaseClass,
                    'placeholder' => 'Sélectionnez la date',

                ],
                'label_attr' => ['class' => $labelBaseClass],
                'constraints' => [
                    new NotBlank(message: 'La date est obligatoire.'),
                ]
            ])
            ->add('name', TextType::class, [
                'label' => 'Nom - champ obligatoire',
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'input pl-12 ' . $inputBaseClass,
                    'placeholder' => 'Saisissez le nom',
                ],

                'label_attr' => ['class' => $labelBaseClass],
                'constraints' => [
                    new NotBlank(message: 'Le nom est obligatoire.'),
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Holiday::class,
        ]);
    }
}
