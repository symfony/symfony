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

use Symfony\Bundle\FrameworkBundle\Routing\Loader\Configurator\Traits\AddTrait;
use Symfony\Component\Routing\Loader\Configurator\RouteConfigurator;

/**
 * @author Jules Pietri <jules@heahprod.com>
 */
class UrlRedirectRouteConfigurator extends RouteConfigurator
{
    use AddTrait;

    /**
     * @param bool $permanent Whether the redirection is permanent
     *
     * @return $this
     */
    final public function permanent(bool $permanent = true)
    {
        return $this->defaults(['permanent' => $permanent]);
    }

    /**
     * @param string|null $scheme The URL scheme (null to keep the current one)
     * @param int|null    $port   The HTTP or HTTPS port (null to keep the current one for the same scheme or the default configured port)
     *
     * @return $this
     */
    final public function scheme(?string $scheme, int $port = null)
    {
        $this->defaults(['scheme' => $scheme]);

        if ('http' === $scheme) {
            $this->defaults(['httpPort' => $port]);
        } elseif ('https' === $scheme) {
            $this->defaults(['httpsPort' => $port]);
        }

        return $this;
    }

    /**
     * @param bool $keepRequestMethod Whether redirect action should keep HTTP request method
     *
     * @return $this
     */
    final public function keepRequestMethod(bool $keepRequestMethod = true)
    {
        return $this->defaults(['keepRequestMethod' => $keepRequestMethod]);
    }
}
