<?php

namespace OagBundle\Form;

use OagBundle\Entity\SuggestedTag;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MergeActivityType extends AbstractType {

    public function buildForm(FormBuilderInterface $builder, array $options) {
        $currentTags = $options['currentTags'];
        $iatiActivityId = $options['iatiActivityId'];
        $file = $options['file'];

        # suggested tags
        $suggestedTags = array();
        foreach ($file->getSuggestedTags() as $sugTag) {
            # if it's not from our activity, ignore it
            if ($sugTag->getActivityId() !== $iatiActivityId) {
                continue;
            }
            $suggestedTags[] = $sugTag;
        }

        # Build the form.
        $builder->add('currentTags', ChoiceType::class, array(
                'expanded' => true,
                'multiple' => true,
                'label_attr' => array('class' => 'biglabel'),
                'choices' => array_keys($currentTags),
                'data' => array_keys($currentTags), // default to ticked
                'choice_label' => function ($value, $key, $index) use ($currentTags) {
                    $desc = $currentTags[$index]['description'];
                    $vocab = $currentTags[$index]['vocabulary'];
                    return "$desc ($vocab)";
                }
            ))
            ->add('suggested', ChoiceType::class, array(
                'expanded' => true,
                'multiple' => true,
                'label_attr' => array('class' => 'biglabel'),
                'choices' => array_reduce($suggestedTags, function ($result, SuggestedTag $item) {
                        # basically changes choices to $item->getTag()->getDescription() => $item->getId()
                        $label = $item->getTag()->getDescription();
                        $result[$label] = $item->getId();
                        return $result;
                    }, array())
        ));

        # Parse each of the enhancing documents into suggested form choices.
        foreach ($file->getEnhancingDocuments() as $otherFile) {
            $name = $otherFile->getDocumentName();
            $tags = $otherFile->getSuggestedTags();
            $id = $otherFile->getId();

            $builder->add("enhanced_$id", ChoiceType::class, array(
                'expanded' => true,
                'multiple' => true,
                'label' => $name,
                'choices' => array_reduce($tags->toArray(), function (array $result, SuggestedTag $item) {
                    # basically changes choices to $item->getTag()->getDescription() => $item->getId()
                    $label = $item->getTag()->getDescription();
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
                'currentTags' => array(),
                'iatiActivityId' => '',
                'file' => NULL,
            )
        );
    }

}
