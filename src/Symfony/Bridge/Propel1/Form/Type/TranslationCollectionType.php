<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Propel1\Form\Type;

use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\Form\Event\DataEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 * form type for i18n-columns in propel
 *
 * @author Patrick Kaufmann
 */
class TranslationCollectionType extends CollectionType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $languages = $options['languages'];
        $i18nClass = $options['i18n_class'];

        $options['options']['data_class'] = $i18nClass;
        $options['options']['columns'] = $options['columns'];

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function(DataEvent $event) use ($builder, $languages, $i18nClass) {
            $form = $event->getForm();
            $data = $event->getData();

            if ($data == null) {
                return;
            }

            //get the class name of the i18nClass
            $temp = explode('\\', $i18nClass);
            $dataClass = end($temp);

            $rootData = $form->getRoot()->getData();

            //add a row for every needed language
            foreach ($languages as $lang) {
                $found = false;

                foreach ($data as $i18n) {
                    if ($i18n->getLocale() == $lang) {
                        $found = true;
                        break;
                    }
                }

                if (!$found) {
                    $newTranslation = new $i18nClass();
                    $newTranslation->setLocale($lang);

                    $addFunction = 'add'.$dataClass;
                    $rootData->$addFunction($newTranslation);
                }
            }
        });

        parent::buildForm($builder, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'translatable_collection';
    }

    /**
     * {@inheritdoc}
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        parent::setDefaultOptions($resolver);
        $resolver->setDefaults(array(
            'languages' => array(),
            'i18n_class' => '',
            'columns' => array(),
            'type' => 'translation_type',
            'allow_add' => false,
            'allow_delete' => false
        ));
    }
}
