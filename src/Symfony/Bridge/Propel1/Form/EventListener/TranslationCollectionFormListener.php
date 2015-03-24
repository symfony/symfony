<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Propel1\Form\EventListener;

use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\FormEvent;

/**
 * listener class for propel1_translatable_collection.
 *
 * @author Patrick Kaufmann
 */
class TranslationCollectionFormListener implements EventSubscriberInterface
{
    private $i18nClass;
    private $languages;

    public function __construct($languages, $i18nClass)
    {
        $this->i18nClass = $i18nClass;
        $this->languages = $languages;
    }

    public static function getSubscribedEvents()
    {
        return array(
            FormEvents::PRE_SET_DATA => array('preSetData', 1),
        );
    }

    public function preSetData(FormEvent $event)
    {
        $form = $event->getForm();
        $data = $event->getData();

        if (null === $data) {
            return;
        }

        if (!is_array($data) && !($data instanceof \Traversable && $data instanceof \ArrayAccess)) {
            throw new UnexpectedTypeException($data, 'array or (\Traversable and \ArrayAccess)');
        }

        //get the class name of the i18nClass
        $temp = explode('\\', $this->i18nClass);
        $dataClass = end($temp);

        $rootData = $form->getRoot()->getData();
        $foundData = false;

        $addFunction = 'add'.$dataClass;

        //add a database row for every needed language
        foreach ($this->languages as $lang) {
            $found = false;

            foreach ($data as $i18n) {
                if (!method_exists($i18n, 'getLocale')) {
                    throw new UnexpectedTypeException($i18n, 'Propel i18n object');
                }

                if ($i18n->getLocale() == $lang) {
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $currentForm = $form;
                while (!$foundData) {
                    if (method_exists($rootData, $addFunction)) {
                        $foundData = true;
                        break;
                    } elseif ($currentForm->hasParent()) {
                        $currentForm = $currentForm->getParent();
                        $rootData = $currentForm->getData();
                    } else {
                        break;
                    }
                }
                if (!$foundData) {
                    throw new UnexpectedTypeException($rootData, 'Propel i18n object');
                }

                $newTranslation = new $this->i18nClass();
                $newTranslation->setLocale($lang);

                $rootData->$addFunction($newTranslation);
            }
        }
    }
}
