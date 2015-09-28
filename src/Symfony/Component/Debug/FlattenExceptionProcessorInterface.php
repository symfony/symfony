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
 * @author Martin Hasoň <martin.hason@gmail.com>
 */
interface FlattenExceptionProcessorInterface
{
    /**
     * Process a flattened exception.
     *
     * @param \Exception       $exception        The raw exception
     * @param FlattenException $flattenException The flattened exception
     * @param bool             $master           Whether it is a master exception
     *
     * @return FlattenException
     */
    public function process(\Exception $exception, FlattenException $flattenException, $master);
}
