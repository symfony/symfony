<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Extension;

use Symfony\Component\Security\Http\Logout\LogoutUrlGenerator;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * LogoutUrlHelper provides generator functions for the logout URL to Twig.
 *
 * @author Jeremy Mikola <jmikola@gmail.com>
 */
final class LogoutUrlExtension extends AbstractExtension
{
    public function __construct(
        private LogoutUrlGenerator $generator,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('logout_url', $this->getLogoutUrl(...)),
            new TwigFunction('logout_path', $this->getLogoutPath(...)),
        ];
    }

    /**
     * Generates the relative logout URL for the firewall.
     *
     * @param string|null $key The firewall key or null to use the current firewall key
     */
    public function getLogoutPath(?string $key = null): string
    {
        return $this->generator->getLogoutPath($key);
    }

    /**
     * Generates the absolute logout URL for the firewall.
     *
     * @param string|null $key The firewall key or null to use the current firewall key
     */
    public function getLogoutUrl(?string $key = null): string
    {
        return $this->generator->getLogoutUrl($key);
    }
}
