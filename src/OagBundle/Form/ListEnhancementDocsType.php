<?php

namespace OagBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ListEnhancementDocsType extends AbstractType {

    public function buildForm(FormBuilderInterface $builder, array $options) {
        $documentNames = $options['documentNames'];

        foreach ($documentNames as $id => $name) {
            $builder->add($id, CheckboxType::class, array(
                'label' => $name,
                'value' => $id,
                'required' => false,
            ));
        }

        $builder->add('submit', SubmitType::class);
    }

    public function buildView(FormView $view, FormInterface $form, array $options) {
        $view->vars = array_merge($view->vars, $options);
    }

    public function configureOptions(OptionsResolver $resolver) {
        $resolver->setDefaults(
            array(
                'documentNames' => array(),
                'attr' => array(
                    'class' => 'pure-table pure-table-bordered'
                )
            )
        );
    }

}
