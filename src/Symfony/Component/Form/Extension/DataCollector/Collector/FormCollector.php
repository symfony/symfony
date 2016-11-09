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

use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector as BaseCollector;

/**
 * DataCollector for Form Validation Failures.
 *
 * @author Robert Schönthal <robert.schoenthal@gmail.com>
 */
class FormCollector extends BaseCollector
{
    /**
     * {@inheritDoc}
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        //nothing to do, everything is added with addError()
    }

    /**
     * Adds a Form-Error to the Collector.
     *
     * @param FormInterface $form
     */
    public function addError(FormInterface $form)
    {
        $storeData = array(
            'root'   => $form->getRoot()->getName(),
            'name'   => (string)$form->getPropertyPath(),
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
     * Returns the number of invalid Forms.
     *
     * @return int
     */
    public function getDataCount()
    {
        return count($this->data);
    }
}
