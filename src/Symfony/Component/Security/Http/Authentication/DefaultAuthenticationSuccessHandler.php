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
    /** @deprecated since Symfony 5.2, use $firewallName instead */
    protected $providerKey;
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

    /**
     * {@inheritdoc}
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token)
    {
        return $this->httpUtils->createRedirectResponse($request, $this->determineTargetUrl($request));
    }

    /**
     * Gets the options.
     *
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    public function setOptions(array $options)
    {
        $this->options = array_merge($this->defaultOptions, $options);
    }

    /**
     * Get the provider key.
     *
     * @return string
     *
     * @deprecated since Symfony 5.2, use getFirewallName() instead
     */
    public function getProviderKey()
    {
        if (1 !== \func_num_args() || true !== func_get_arg(0)) {
            trigger_deprecation('symfony/security-core', '5.2', 'Method "%s()" is deprecated, use "getFirewallName()" instead.', __METHOD__);
        }

        if ($this->providerKey !== $this->firewallName) {
            trigger_deprecation('symfony/security-core', '5.2', 'The "%1$s::$providerKey" property is deprecated, use "%1$s::$firewallName" instead.', __CLASS__);

            return $this->providerKey;
        }

        return $this->firewallName;
    }

    public function setProviderKey(string $providerKey)
    {
        if (2 !== \func_num_args() || true !== func_get_arg(1)) {
            trigger_deprecation('symfony/security-http', '5.2', 'Method "%s" is deprecated, use "setFirewallName()" instead.', __METHOD__);
        }

        $this->providerKey = $providerKey;
    }

    public function getFirewallName(): ?string
    {
        return $this->getProviderKey(true);
    }

    public function setFirewallName(string $firewallName): void
    {
        $this->setProviderKey($firewallName, true);

        $this->firewallName = $firewallName;
    }

    /**
     * Builds the target URL according to the defined options.
     *
     * @return string
     */
    protected function determineTargetUrl(Request $request)
    {
        if ($this->options['always_use_default_target_path']) {
            return $this->options['default_target_path'];
        }

        $targetUrl = ParameterBagUtils::getRequestParameterValue($request, $this->options['target_path_parameter']);

        if (\is_string($targetUrl) && str_starts_with($targetUrl, '/')) {
            return $targetUrl;
        }

        if ($this->logger && $targetUrl) {
            $this->logger->debug(sprintf('Ignoring query parameter "%s": not a valid URL.', $this->options['target_path_parameter']));
        }

        $firewallName = $this->getFirewallName();
        if (null !== $firewallName && $targetUrl = $this->getTargetPath($request->getSession(), $firewallName)) {
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
