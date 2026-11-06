<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\Routing;

use Symfony\Bundle\TwigBundle\Controller\TemplateController;
use Symfony\Component\Routing\RouteCollection;

/**
 * Turns the "_template" default of routes, set by their "template" key, into a call to TemplateController.
 *
 * @internal
 */
final class TemplateRouteProcessor
{
    public function __invoke(RouteCollection $routes): void
    {
        foreach ($routes->all() as $name => $route) {
            // a controller set by an import wins, the same way it wins over the "controller" key of the route
            if (null === ($template = $route->getDefault('_template')) || $route->hasDefault('_controller')) {
                continue;
            }

            if ($route->hasDefault('template')) {
                throw new \InvalidArgumentException(\sprintf('The route "%s" must not define both the "_template" default and the "template" default.', $name));
            }

            if (\in_array('template', $route->compile()->getVariables(), true)) {
                throw new \InvalidArgumentException(\sprintf('The route "%s" must not define both the "_template" default and a "{template}" placeholder.', $name));
            }

            $defaults = $route->getDefaults();
            unset($defaults['_template']);
            $route->setDefaults(['_controller' => TemplateController::class.'::templateAction', 'template' => $template] + $defaults);
        }
    }
}
