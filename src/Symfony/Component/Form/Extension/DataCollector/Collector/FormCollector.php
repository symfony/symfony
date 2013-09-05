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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector as BaseCollector;

/**
 * DataCollector for Form Validation Failures
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
     * adds a form error to the collector
     *
     * @param array $data
     */
    public function addError(array $data)
    {
        $data['value'] = $this->varToString($data['value']);
        $this->data[$data['root']][$data['name']] = $data;
    }

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return 'form';
    }

    public function getData()
    {
        return $this->data;
    }

    /**
     * returns the number of invalid forms
     *
     * @return int
     */
    public function getDataCount()
    {
        return count($this->data);
    }
}
