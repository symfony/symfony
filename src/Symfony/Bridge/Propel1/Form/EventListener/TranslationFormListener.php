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

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

/**
 * Event Listener class for propel1_translation.
 *
 * @author Patrick Kaufmann
 */
class TranslationFormListener implements EventSubscriberInterface
{
    private $columns;
    private $dataClass;

    public function __construct($columns, $dataClass)
    {
        $this->columns = $columns;
        $this->dataClass = $dataClass;
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

        if (!$data instanceof $this->dataClass) {
            return;
        }

        //loop over all columns and add the input
        foreach ($this->columns as $column => $options) {
            if (is_string($options)) {
                $column = $options;
                $options = array();
            }
            if (null === $options) {
                $options = array();
            }

            $type = 'text';
            if (array_key_exists('type', $options)) {
                $type = $options['type'];
            }
            $label = $column;
            if (array_key_exists('label', $options)) {
                $label = $options['label'];
            }

            $customOptions = array();
            if (array_key_exists('options', $options)) {
                $customOptions = $options['options'];
            }
            $options = array(
                'label' => $label.' '.strtoupper($data->getLocale()),
            );

            $options = array_merge($options, $customOptions);

            $form->add($column, $type, $options);
        }
    }
}
