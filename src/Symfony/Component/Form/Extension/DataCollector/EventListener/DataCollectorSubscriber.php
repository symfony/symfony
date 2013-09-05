<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\DataCollector\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;

/**
 * EventSubscriber for Form Validation Failures
 *
 * @author Robert Schönthal <robert.schoenthal@gmail.com>
 */
class DataCollectorSubscriber implements EventSubscriberInterface
{
    /**
     * @var DataCollectorInterface
     */
    private $collector;

    public function __construct(DataCollectorInterface $collector)
    {
        $this->collector = $collector;
    }

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents()
    {
        return array(FormEvents::POST_SUBMIT => array('addToProfiler', -255));
    }

    /**
     * Validates the form and its domain object.
     *
     * @param FormEvent $event The event object
     */
    public function addToProfiler(FormEvent $event)
    {
        $form = $event->getForm();

        if ($form->isRoot() && !$form->isValid()) {
            //add global errors
            $this->addErrors($form, true);

            //add field errors
            foreach ($form->all() as $field) {
                $this->addErrors($field);
            }
        }
    }

    /**
     * adds errors to the collector
     *
     * @param FormInterface $form
     * @param bool $global if collect errors as globals
     */
    private function addErrors(FormInterface $form, $global = false)
    {
        if (!$form->getErrors()) {
            return;
        }

        $this->collector->addError(array(
            'root'   => $form->getRoot()->getName(),
            'name'   => (string)$form->getPropertyPath(),
            'type'   => $form->getConfig()->getType()->getName(),
            'errors' => $form->getErrors(),
            'value'  => $global ? 'global' : $form->getViewData()
        ));
    }
}
