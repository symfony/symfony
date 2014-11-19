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
 * ExceptionFlattener flattens the exceptions
 *
 * @author Martin Hasoň <martin.hason@gmail.com>
 */
interface ExceptionFlattenerInterface
{
    /**
     * Flattens an exception
     *
     * @param \Exception       $exception        The raw exception
     * @param FlattenException $flattenException The flattened exception
     * @param array            $options          The options
     *
     * @return FlattenException
     */
    public function flatten(\Exception $exception, FlattenException $flattenException, $options = array());
}
