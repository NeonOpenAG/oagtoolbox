<?php

namespace OagBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SectorEditType extends AbstractType {
  
  public function buildForm(FormBuilderInterface $builder, array $options) {
    $allCurSectors = $options['currentSectors'];
    $allNewSectors = $options['newSectors'];

    foreach (array_keys($allCurSectors) as $id) {
      $curSectors = $allCurSectors[$id];
      $newSectors = $allNewSectors[$id];

      $curChoices = array();
      foreach ($curSectors as $sector) {
        $curChoices[$sector['description']] = $sector['code'];
      }
      $builder->add('current' . $id, ChoiceType::class, array(
        'expanded' => true,
        'multiple' => true,
        'choices' => $curChoices,
        'data' => array_values($curChoices) // tick all by default
      ));

      $newChoices = array();
      foreach ($newSectors as $sector) {
        $newChoices[$sector['description']] = $sector['code'];
      }
      $builder->add('new' . $id, ChoiceType::class, array(
        'expanded' => true,
        'multiple' => true,
        'choices' => $newChoices
      ));
    }

    $builder->add('submit', SubmitType::class);
  }

  public function configureOptions(OptionsResolver $resolver) {
    $resolver->setDefaults(array(
      'currentSectors' => array(),
      'newSectors' => array()
    ));
  }

}
