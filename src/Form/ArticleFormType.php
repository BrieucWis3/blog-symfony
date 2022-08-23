<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use App\Entity\Article;
use App\Entity\Keyword;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Validator\Constraints\File;
use App\AdminBundle\Form\ImageType;

class ArticleFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                    'attr' => [
                            'class' => 'form-control'
                        ],
                        'label' => 'Titre'
            ])    
            ->add('subject', TextType::class, [
                    'attr' => [
                            'class' => 'form-control'
                        ],
                        'label' => 'Sujet'
            ])
            ->add('keywords', EntityType::class, [
                'class' => Keyword::class,
                'attr' => [
                            'class' => 'form-control'
                            //'id' => 'choices-multiple-remove-button'
                        ],
                'choice_label' => 'name',
                'label' => 'Thème',
                'multiple' => true,
                //'required' => false
            ])
            ->add('textKeyword', TextType::class, [
                // instead of being set onto the object directly,
                // this is read and encoded in the controller
                'mapped' => false,
                'attr' => [
                            'class' => 'form-control'
                          ],
                'constraints' => [
                    /*new NotBlank([
                        'message' => 'Please enter a keyword',
                    ]),*/
                    new Length([
                        'min' => 3,
                        'minMessage' => 'Your password should be at least {{ limit }} characters',
                        // max length allowed by Symfony for security reasons
                        'max' => 4096,
                    ]),
                ],
                'label' => 'Ajouter un thème',
                'required' => false
            ])
            ->add('content', TextareaType::class, [
                    'attr' => [
                            'class' => 'form-control'
                        ],
                        'label' => 'Contenu'
            ])
            ->add('image', FileType::class, [
                'attr' => [
                            'class' => 'form-control'
                        ],
                /*'constraints' => [
                    new File([
                        'maxSize' => '1024k',
                        'mimeTypes' => [
                            'image/*'
                        ],
                        'mimeTypesMessage' => 'Please upload a valid image file',
                    ])
                ],*/
                'label' => false,
                'mapped' => false
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Article::class,
        ]);
    }
}

