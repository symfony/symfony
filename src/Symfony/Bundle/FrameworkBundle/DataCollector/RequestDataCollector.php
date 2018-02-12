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

@trigger_error(sprintf('The "%s" class is deprecated since version 4.1 and will be removed in Symfony 5.0. Use %s instead.', RequestDataCollector::class, BaseRequestDataCollector::class), E_USER_DEPRECATED);

/**
 * RequestDataCollector.
 *
 * @author Jules Pietri <jusles@heahprod.com>
 *
 * @deprecated since version 4.1, to be removed in Symfony 5.0
 */
class RequestDataCollector extends BaseRequestDataCollector
{
}
