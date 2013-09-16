<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\DataCollector\Collector;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector as BaseCollector;

/**
 * DataCollector for Form Validation.
 *
 * @author Robert Schönthal <robert.schoenthal@gmail.com>
 */
class FormCollector extends BaseCollector implements EventSubscriberInterface
{
    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents()
    {
        return array(FormEvents::POST_SUBMIT => array('collectForm', -255));
    }

    /**
     * {@inheritDoc}
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        //nothing to do, everything is added with addError()
    }

    /**
     * Collects Form-Validation-Data and adds them to the Collector.
     *
     * @param FormEvent $event The event object
     */
    public function collectForm(FormEvent $event)
    {
        $form = $event->getForm();

        if ($form->isRoot()) {
            $this->data[$form->getName()] = array();
            $this->addForm($form);
        }
    }

    /**
     * Adds an Form-Element to the Collector.
     *
     * @param FormInterface $form
     */
    private function addForm(FormInterface $form)
    {
        if ($form->getErrors()) {
            $this->addError($form);
        }

        // recursively add all child-errors
        foreach ($form->all() as $field) {
            $this->addForm($field);
        }
    }

    /**
     * Adds a Form-Error to the Collector.
     *
     * @param FormInterface $form
     */
    private function addError(FormInterface $form)
    {
        $storeData = array(
            'root'   => $form->getRoot()->getName(),
            'name'   => (string) $form->getPropertyPath(),
            'type'   => $form->getConfig()->getType()->getName(),
            'errors' => $form->getErrors(),
            'value'  => $this->varToString($form->getViewData())
        );

        $this->data[$storeData['root']][$storeData['name']] = $storeData;
    }

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return 'form';
    }

    /**
     * Returns all collected Data.
     *
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Returns the number of Forms with Errors.
     *
     * @return integer
     */
    public function getErrorCount()
    {
        $errorCount = 0;

        foreach ($this->data as $form) {
            if (count($form)) {
                $errorCount++;
            }
        }

        return $errorCount;
    }
}
