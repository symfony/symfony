<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\DataCollector;

use Symfony\Bundle\FrameworkBundle\Profiler\DataCollector\AjaxDataCollector as BaseAjaxDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

@trigger_error('The '.__NAMESPACE__.'\AjaxDataCollector class is deprecated since version 2.8 and will be removed in 3.0. Use Symfony\Bundle\FrameworkBundle\Profiler\DataCollector\AjaxDataCollector instead.', E_USER_DEPRECATED);

/**
 * AjaxDataCollector.
 *
 * @author Bart van den Burg <bart@burgov.nl>
 *
 * @deprecated since version 2.8, to be removed in 3.0.
 */
class AjaxDataCollector extends BaseAjaxDataCollector
{
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        // all collecting is done client side
    }

    public function getName()
    {
        return 'ajax';
    }
}
