<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Logout;

/**
 * Generate the absolute logout path and URL for a firewall.
 */
interface LogoutUrlGeneratorInterface
{
    /**
     * Generates the absolute logout path for the firewall.
     *
     * @param string|null $key The firewall name (null to generate a path for the current firewall)
     */
    public function getLogoutPath(string $key = null): string;

    /**
     * Generates the absolute logout URL for the firewall.
     *
     * @param string|null $key The firewall name (null to generate a URL for the current firewall)
     */
    public function getLogoutUrl(string $key = null): string;
}
