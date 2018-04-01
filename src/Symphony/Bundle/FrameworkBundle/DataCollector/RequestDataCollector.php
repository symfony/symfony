<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\FrameworkBundle\DataCollector;

use Symphony\Component\HttpKernel\DataCollector\RequestDataCollector as BaseRequestDataCollector;

@trigger_error(sprintf('The "%s" class is deprecated since Symphony 4.1. Use %s instead.', RequestDataCollector::class, BaseRequestDataCollector::class), E_USER_DEPRECATED);

/**
 * RequestDataCollector.
 *
 * @author Jules Pietri <jusles@heahprod.com>
 *
 * @deprecated since Symphony 4.1
 */
class RequestDataCollector extends BaseRequestDataCollector
{
}
