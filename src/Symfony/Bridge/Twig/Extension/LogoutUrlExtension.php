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
class LogoutUrlExtension extends AbstractExtension
{
    private $generator;

    public function __construct(LogoutUrlGenerator $generator)
    {
        $this->generator = $generator;
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions()
    {
        return [
            new TwigFunction('logout_url', [$this, 'getLogoutUrl']),
            new TwigFunction('logout_path', [$this, 'getLogoutPath']),
        ];
    }

    /**
     * Generates the relative logout URL for the firewall.
     *
     * @param string|null $key The firewall key or null to use the current firewall key
     *
     * @return string The relative logout URL
     */
    public function getLogoutPath($key = null)
    {
        return $this->generator->getLogoutPath($key);
    }

    /**
     * Generates the absolute logout URL for the firewall.
     *
     * @param string|null $key The firewall key or null to use the current firewall key
     *
     * @return string The absolute logout URL
     */
    public function getLogoutUrl($key = null)
    {
        return $this->generator->getLogoutUrl($key);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'logout_url';
    }
}
