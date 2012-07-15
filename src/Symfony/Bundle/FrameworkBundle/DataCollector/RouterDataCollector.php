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

use Symfony\Component\HttpKernel\DataCollector\RouterDataCollector as BaseRouterDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\RedirectController;

/**
 * RouterDataCollector.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class RouterDataCollector extends BaseRouterDataCollector
{
    public function getRoute(Request $request, $controller)
    {
        if ($controller instanceof RedirectController) {
            return $request->attributes->get('_route');
        }
    }
}
