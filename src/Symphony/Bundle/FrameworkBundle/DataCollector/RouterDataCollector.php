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

use Symphony\Component\HttpKernel\DataCollector\RouterDataCollector as BaseRouterDataCollector;
use Symphony\Component\HttpFoundation\Request;
use Symphony\Bundle\FrameworkBundle\Controller\RedirectController;

/**
 * RouterDataCollector.
 *
 * @author Fabien Potencier <fabien@symphony.com>
 */
class RouterDataCollector extends BaseRouterDataCollector
{
    public function guessRoute(Request $request, $controller)
    {
        if (is_array($controller)) {
            $controller = $controller[0];
        }

        if ($controller instanceof RedirectController) {
            return $request->attributes->get('_route');
        }

        return parent::guessRoute($request, $controller);
    }
}
