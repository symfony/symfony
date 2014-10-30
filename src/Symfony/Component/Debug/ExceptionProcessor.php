<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Debug;

use Symfony\Component\Debug\Exception\FlattenException;

/**
 * ExceptionProcessor process the exceptions
 *
 * @author Martin Hasoň <martin.hason@gmail.com>
 */
class ExceptionProcessor
{
    /**
     * @var ExceptionFlattenerInterface[]
     */
    private $flatteners = array();

    /**
     * @param array $flatteners The array of flatteners
     */
    public function __construct($flatteners = array())
    {
        foreach ($flatteners as $flattener) {
            $this->addFlattener($flattener);
        }
    }

    /**
     * Adds an exception flattener
     *
     * @param ExceptionFlattenerInterface $flattener
     */
    public function addFlattener(ExceptionFlattenerInterface $flattener)
    {
        $this->flatteners[] = $flattener;
    }

    /**
     * Flattens an exception
     *
     * @param \Exception $exception The raw exception
     * @param array      $options   The options
     *
     * @return FlattenException
     */
    public function flatten(\Exception $exception, $options = array())
    {
        $options = array_merge(array(
            'class' => 'Symfony\Component\Debug\Exception\FlattenException',
        ), $options);

        $e = call_user_func(array($options['class'], 'create'), $exception);
        foreach ($this->flatteners as $flattener) {
            $e = $flattener->flatten($exception, $e, $options);
        }

        return $e;
    }
}
