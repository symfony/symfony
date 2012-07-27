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
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\FormEvent;

/**
 * listener class for propel1_translatable_collection
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

        if(null == $data)
        {
            return;
        }

        //get the class name of the i18nClass
        $temp = explode('\\', $this->i18nClass);
        $dataClass = end($temp);

        $rootData = $form->getRoot()->getData();

        //add a database row for every needed language
        foreach($this->languages as $lang)
        {
            $found = false;

            foreach($data as $i18n)
            {
                if($i18n->getLocale() == $lang)
                {
                    $found = true;
                    break;
                }
            }

            if(!$found)
            {
                $newTranslation = new $this->i18nClass();
                $newTranslation->setLocale($lang);

                $addFunction = 'add'.$dataClass;
                $rootData->$addFunction($newTranslation);
            }
        }
    }
}
