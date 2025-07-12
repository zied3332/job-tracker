<?php

namespace App\Form;

use App\Entity\Job;
use App\Entity\Company;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\OptionsResolver\OptionsResolver;

class JobTypeForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('claimNumber', TextType::class, [
                'constraints' => [
                    new NotBlank(['message' => 'Claim number is required']),
                    new Length(['max' => 30]),
                ],
                'empty_data' => ''
            ])
            ->add('address', TextType::class, [
                'constraints' => [
                    new NotBlank(['message' => 'Address is required']),
                    new Length(['max' => 255]),
                ],
                'empty_data' => ''
            ])
            ->add('state', TextType::class, [
                'constraints' => [
                    new NotBlank(['message' => 'State is required']),
                    new Length(['max' => 50]),
                ],
                'empty_data' => ''
            ])
            ->add('city', TextType::class, [
                'constraints' => [
                    new NotBlank(['message' => 'City is required']),
                    new Length(['max' => 50]),
                ],
                'empty_data' => ''
            ])
            ->add('projectManager', TextType::class, [
                'constraints' => [
                    new NotBlank(['message' => 'Project manager is required']),
                    new Length(['max' => 50]),
                ],
                'empty_data' => ''
            ])
           
            ->add('category', ChoiceType::class, [
                'choices' => [
                    'Emergency' => 'Emergency',
                    'Hazardous' => 'Hazardous',
                    'Repair' => 'Repair',
                    'Flooring' => 'Flooring',
                    'Other' => 'Other',
                ],
                'placeholder' => 'Select a category',
                'constraints' => [
                    new NotBlank(['message' => 'Category is required']),
                ],
                'empty_data' => ''
            ])
            ->add('company', EntityType::class, [
                'class' => Company::class,
                'choice_label' => 'name',
                'placeholder' => 'Select a company',
                'by_reference' => true,
                'constraints' => [
                    new NotBlank(['message' => 'Company is required']),
                ],
            ])
            ->add('name', TextType::class, [
                'constraints' => [
                    new NotBlank(['message' => 'Job name is required']),
                    new Length(['max' => 255]),
                ],
                'empty_data' => ''
            ])
            ->add('customer', TextType::class, [
                'constraints' => [
                    new NotBlank(['message' => 'Customer name is required']),
                    new Length(['max' => 255]),
                ],
                'empty_data' => ''
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
                'label' => 'Job Description',
                'attr' => ['rows' => 4],
                'empty_data' => '',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Job::class,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return ''; // Disables the "job[...]" prefix
    }
}
