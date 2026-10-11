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
use Twig\Attribute\AsTwigFunction;
use Twig\Extension\AbstractExtension;
use Twig\Extension\AttributeExtension;
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

    /**
     * @deprecated since Symfony 8.2, register this class with "new AttributeExtension(LogoutUrlExtension::class)" instead
     */
    public function getFunctions(): array
    {
        trigger_deprecation('symfony/twig-bridge', '8.2', 'The "%s::getFunctions()" method is deprecated, register this class with "new %s(%s::class)" instead.', self::class, AttributeExtension::class, self::class);

        return [
            new TwigFunction('logout_url', $this->getLogoutUrl(...)),
            new TwigFunction('logout_path', $this->getLogoutPath(...)),
            new TwigFunction('logout_form', $this->getLogoutForm(...)),
        ];
    }

    /**
     * Generates the relative logout URL for the firewall.
     *
     * @param string|null $key The firewall key or null to use the current firewall key
     */
    #[AsTwigFunction('logout_path')]
    public function getLogoutPath(?string $key = null): string
    {
        return $this->generator->getLogoutPath($key);
    }

    /**
     * Generates the absolute logout URL for the firewall.
     *
     * @param string|null $key The firewall key or null to use the current firewall key
     */
    #[AsTwigFunction('logout_url')]
    public function getLogoutUrl(?string $key = null): string
    {
        return $this->generator->getLogoutUrl($key);
    }

    /**
     * Returns the action and the hidden fields of a form triggering the logout.
     *
     * @param string|null $key The firewall key or null to use the current firewall key
     *
     * @return array{action: string, fields: array<string, string>}
     */
    #[AsTwigFunction('logout_form')]
    public function getLogoutForm(?string $key = null): array
    {
        return $this->generator->getLogoutForm($key);
    }
}
