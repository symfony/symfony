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
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * LogoutUrlHelper provides generator functions for the logout URL to Twig.
 *
 * @author Jeremy Mikola <jmikola@gmail.com>
 */
final class LogoutUrlExtension extends AbstractExtension
{
    /**
     * @param array<string, mixed> $csrfFieldAttr The HTML attributes of the hidden field that contains the CSRF token
     */
    public function __construct(
        private LogoutUrlGenerator $generator,
        private array $csrfFieldAttr = [],
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('logout_url', $this->getLogoutUrl(...)),
            new TwigFunction('logout_path', $this->getLogoutPath(...)),
            new TwigFunction('logout_form', $this->getLogoutForm(...)),
            new TwigFunction('logout_button', $this->renderLogoutButton(...), ['needs_environment' => true, 'is_safe' => ['html']]),
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
     * @return array{action: string, fields: array<string, string>, csrf_field: string|null}
     */
    #[AsTwigFunction('logout_form')]
    public function getLogoutForm(?string $key = null): array
    {
        return $this->generator->getLogoutForm($key);
    }

    /**
     * Renders a form that triggers the logout with a POST.
     *
     * @param string|\Stringable   $label    The label of the button, escaped unless it is a Twig Markup
     * @param string|null          $key      The firewall key or null to use the current firewall key
     * @param array<string, mixed> $attr     The HTML attributes of the button
     * @param array<string, mixed> $formAttr The HTML attributes of the form
     */
    #[AsTwigFunction('logout_button', isSafe: ['html'])]
    public function renderLogoutButton(Environment $env, string|\Stringable $label = 'Log out', ?string $key = null, array $attr = [], array $formAttr = []): string
    {
        return PostFormRenderer::render($env, $this->generator->getLogoutForm($key), $label, $attr, $formAttr, $this->csrfFieldAttr);
    }
}
