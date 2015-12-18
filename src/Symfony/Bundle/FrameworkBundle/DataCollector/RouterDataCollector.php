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

use Symfony\Bundle\FrameworkBundle\Profiler\DataCollector\RouterDataCollector as BaseRouterDataCollector;

@trigger_error('The '.__NAMESPACE__.'\RouterDataCollector class is deprecated since version 2.8 and will be removed in 3.0. Use Symfony\Bundle\FrameworkBundle\Profiler\DataCollector\RouterDataCollector  instead.', E_USER_DEPRECATED);

/**
 * RouterDataCollector.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @deprecated Deprecated since Symfony 2.8, to be removed in Symfony 3.0.
 *             Use {@link Symfony\Bundle\FrameworkBundle\Profiler\DataCollector\RouterDataCollector} instead.
 */
class RouterDataCollector extends BaseRouterDataCollector
{
}
