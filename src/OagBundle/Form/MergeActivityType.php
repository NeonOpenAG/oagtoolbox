<?php

namespace OagBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MergeActivityType extends AbstractType {

  public function buildForm(FormBuilderInterface $builder, array $options) {
    $ids = $options['ids'];
    $allCur = $options['current'];
    $allNew = $options['new'];

    foreach (array_keys($ids) as $id) {
      $cur = array_key_exists($id, $allCur) ? $allCur[$id] : array();
      $new = array_key_exists($id, $allNew) ? $allNew[$id] : array();

      $builder->add('current' . $id, ChoiceType::class, array(
        'expanded' => true,
        'multiple' => true,
        'choices' => $cur,
        'data' => array_values($cur) // tick all by default
      ));

      $builder->add('new' . $id, ChoiceType::class, array(
        'expanded' => true,
        'multiple' => true,
        'choices' => $new
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
                'ids' => array(),
                'current' => array(),
                'new' => array(),
            'attr' => array(
                'class' => 'pure-table pure-table-bordered'
                )
            )
        );
    }

}
