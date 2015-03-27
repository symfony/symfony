<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Debug;

use Symfony\Component\Debug\Exception\FlattenException;
use Symfony\Component\Debug\FlattenExceptionProcessorInterface;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * @author Martin Hasoň <martin.hason@gmail.com>
 */
class HttpFlattenExceptionProcessor implements FlattenExceptionProcessorInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(\Exception $exception, FlattenException $flattenException, $master)
    {
        if (!$exception instanceof HttpExceptionInterface) {
            $flattenException->setStatusCode($flattenException->getStatusCode() ?: 500);

            return;
        }

        $flattenException->setStatusCode($exception->getStatusCode() ?: 500);
        $flattenException->setHeaders(array_merge($flattenException->getHeaders(), $exception->getHeaders()));
    }
}
