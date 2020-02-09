<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Routing\Loader\Configurator;

use Symfony\Bundle\FrameworkBundle\Controller\RedirectController;
use Symfony\Bundle\FrameworkBundle\Controller\TemplateController;
use Symfony\Component\Routing\Loader\Configurator\RouteConfigurator as BaseRouteConfigurator;

/**
 * @author Jules Pietri <jules@heahprod.com>
 */
class RouteConfigurator extends BaseRouteConfigurator
{
    /**
     * @param string $template The template name
     * @param array  $context  The template variables
     */
    final public function template(string $template, array $context = []): TemplateRouteConfigurator
    {
        return (new TemplateRouteConfigurator($this->collection, $this->route, $this->name, $this->parentConfigurator, $this->prefixes))
            ->defaults([
                '_controller' => TemplateController::class,
                'template' => $template,
                'context' => $context,
            ])
        ;
    }

    /**
     * @param string $route The route name to redirect to
     */
    final public function redirectToRoute(string $route): RedirectRouteConfigurator
    {
        return (new RedirectRouteConfigurator($this->collection, $this->route, $this->name, $this->parentConfigurator, $this->prefixes))
            ->defaults([
                '_controller' => RedirectController::class.'::redirectAction',
                'route' => $route,
            ])
        ;
    }

    /**
     * @param string $url The relative path or URL to redirect to
     */
    final public function redirectToUrl(string $url): UrlRedirectRouteConfigurator
    {
        return (new UrlRedirectRouteConfigurator($this->collection, $this->route, $this->name, $this->parentConfigurator, $this->prefixes))
            ->defaults([
                '_controller' => RedirectController::class.'::urlRedirectAction',
                'path' => $url,
            ])
        ;
    }

    final public function gone(): GoneRouteConfigurator
    {
        return (new GoneRouteConfigurator($this->collection, $this->route, $this->name, $this->parentConfigurator, $this->prefixes))
            ->defaults([
                '_controller' => RedirectController::class.'::redirectAction',
                'route' => '',
            ])
        ;
    }
}
