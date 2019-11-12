<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\DataCollector;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * AjaxDataCollector.
 *
 * @author Bart van den Burg <bart@burgov.nl>
 *
 * @final since Symfony 4.4
 */
class AjaxDataCollector extends DataCollector
{
    /**
     * {@inheritdoc}
     *
     * @param \Throwable|null $exception
     */
    public function collect(Request $request, Response $response/*, \Throwable $exception = null*/)
    {
        // all collecting is done client side
    }

    public function reset()
    {
        // all collecting is done client side
    }

    public function getName()
    {
        return 'ajax';
    }
}
