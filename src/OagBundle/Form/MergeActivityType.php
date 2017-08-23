<?php

namespace OagBundle\Form;

use OagBundle\Entity\SuggestedSector;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MergeActivityType extends AbstractType {

    public function buildForm(FormBuilderInterface $builder, array $options) {
        $currentSectors = $options['currentSectors'];
        $iatiActivityId = $options['iatiActivityId'];
        $file = $options['file'];

        # suggested sectors
        $suggestedSectors = array();
        foreach ($file->getSuggestedSectors() as $sugSector) {
            # if it's not from our activity, ignore it
            if ($sugSector->getActivityId() !== $iatiActivityId) {
                continue;
            }
            $suggestedSectors[] = $sugSector;
        }

        # Build the form.
        $builder->add('currentSectors', ChoiceType::class, array(
            'expanded' => true,
            'multiple' => true,
            'choices' => array_keys($currentSectors),
            'data' => array_keys($currentSectors), // default to ticked
            'choice_label' => function ($value, $key, $index) use ($currentSectors) {
                $desc = $currentSectors[$index]['description'];
                $vocab = $currentSectors[$index]['vocabulary'];
                return "$desc ($vocab)";
            }
        ))
        ->add('suggested', ChoiceType::class, array(
            'expanded' => true,
            'multiple' => true,
            'choices' => array_reduce($suggestedSectors, function ($result, SuggestedSector $item) {
                # basically changes choices to $item->getSector()->getDescription() => $item->getId()
                $label = $item->getSector()->getDescription();
                $result[$label] = $item->getId();
                return $result;
            }, array())
        ));

        # Parse each of the enhancing documents into suggested form choices.
        foreach ($file->getEnhancingDocuments() as $otherFile) {
            $name = $otherFile->getDocumentName();
            $sectors = $otherFile->getSuggestedSectors();
            $id = $otherFile->getId();

            $builder->add("enhanced_$id", ChoiceType::class, array(
                'expanded' => true,
                'multiple' => true,
                'label' => $name,
                'choices' => array_reduce($sectors->toArray(), function (array $result, SuggestedSector $item) {
                    # basically changes choices to $item->getSector()->getDescription() => $item->getId()
                    $label = $item->getSector()->getDescription();
                    $result[$label] = $item->getId();
                    return $result;
                }, array())
            ));
        }

        $builder->add('submit', SubmitType::class, array(
            'label' => 'Merge'
        ));
    }

    public function buildView(FormView $view, FormInterface $form, array $options) {
        $view->vars = array_merge($view->vars, $options);
    }

    public function configureOptions(OptionsResolver $resolver) {
        $resolver->setDefaults(
            array(
                'currentSectors' => array(),
                'iatiActivityId' => '',
                'file' => NULL,
            )
        );
    }

}
