<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Authentication;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\HttpUtils;
use Symfony\Component\Security\Http\ParameterBagUtils;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

/**
 * Class with the default authentication success handling logic.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 * @author Alexander <iam.asm89@gmail.com>
 */
class DefaultAuthenticationSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    use TargetPathTrait;

    protected $httpUtils;
    protected $logger;
    protected $options;
    protected $firewallName;
    protected $defaultOptions = [
        'always_use_default_target_path' => false,
        'default_target_path' => '/',
        'login_path' => '/login',
        'target_path_parameter' => '_target_path',
        'use_referer' => false,
    ];

    /**
     * @param array $options Options for processing a successful authentication attempt
     */
    public function __construct(HttpUtils $httpUtils, array $options = [], LoggerInterface $logger = null)
    {
        $this->httpUtils = $httpUtils;
        $this->logger = $logger;
        $this->setOptions($options);
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): ?Response
    {
        return $this->httpUtils->createRedirectResponse($request, $this->determineTargetUrl($request));
    }

    /**
     * Gets the options.
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @return void
     */
    public function setOptions(array $options)
    {
        $this->options = array_merge($this->defaultOptions, $options);
    }

    public function getFirewallName(): ?string
    {
        return $this->firewallName;
    }

    public function setFirewallName(string $firewallName): void
    {
        $this->firewallName = $firewallName;
    }

    /**
     * Builds the target URL according to the defined options.
     */
    protected function determineTargetUrl(Request $request): string
    {
        if ($this->options['always_use_default_target_path']) {
            return $this->options['default_target_path'];
        }

        $targetUrl = ParameterBagUtils::getRequestParameterValue($request, $this->options['target_path_parameter']);

        if (\is_string($targetUrl) && (str_starts_with($targetUrl, '/') || str_starts_with($targetUrl, 'http'))) {
            return $targetUrl;
        }

        if ($this->logger && $targetUrl) {
            $this->logger->debug(sprintf('Ignoring query parameter "%s": not a valid URL.', $this->options['target_path_parameter']));
        }

        $firewallName = $this->getFirewallName();
        if (null !== $firewallName && !$request->attributes->getBoolean('_stateless') && $targetUrl = $this->getTargetPath($request->getSession(), $firewallName)) {
            $this->removeTargetPath($request->getSession(), $firewallName);

            return $targetUrl;
        }

        if ($this->options['use_referer'] && $targetUrl = $request->headers->get('Referer')) {
            if (false !== $pos = strpos($targetUrl, '?')) {
                $targetUrl = substr($targetUrl, 0, $pos);
            }
            if ($targetUrl && $targetUrl !== $this->httpUtils->generateUri($request, $this->options['login_path'])) {
                return $targetUrl;
            }
        }

        return $this->options['default_target_path'];
    }
}
