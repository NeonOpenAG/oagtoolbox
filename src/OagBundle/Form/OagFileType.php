<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace OagBundle\Form;

use OagBundle\Entity\OagFile;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

/**
 * Description of OagFileType
 *
 * @author tobias
 */
class OagFileType extends AbstractType {

    public function buildForm(FormBuilderInterface $builder, array $options) {
        $builder->add(
            'documentName', FileType::class, array('label' => false)
        );
        $builder->add('fileType', ChoiceType::class, array(
            'label' => 'Type ',
            'choices' => array(
                'IATI document' => OagFile::OAGFILE_IATI_DOCUMENT,
                'IATI source document' => OagFile::OAGFILE_IATI_SOURCE_DOCUMENT,
                'Enhancement document' => OagFile::OAGFILE_IATI_ENHANCEMENT_DOCUMENT,
            ),
            )
        );
    }

    public function configureOptions(OptionsResolver $resolver) {
        $resolver->setDefaults(array(
            'data_class' => OagFile::class,
        ));
    }

}
