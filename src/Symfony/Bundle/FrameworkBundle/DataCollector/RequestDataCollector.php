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

use Symfony\Component\HttpKernel\DataCollector\RequestDataCollector as BaseRequestDataCollector;

@trigger_error(sprintf('The "%s" class is deprecated since Symfony 4.1. Use %s instead.', RequestDataCollector::class, BaseRequestDataCollector::class), \E_USER_DEPRECATED);

/**
 * RequestDataCollector.
 *
 * @author Jules Pietri <jusles@heahprod.com>
 *
 * @deprecated since Symfony 4.1
 */
class RequestDataCollector extends BaseRequestDataCollector
{
}
