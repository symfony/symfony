<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Templating\Helper;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Form\Extension\Csrf\CsrfProvider\CsrfProviderAdapter;
use Symfony\Component\Form\Extension\Csrf\CsrfProvider\CsrfProviderInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Templating\Helper\Helper;

/**
 * LogoutUrlHelper provides generator functions for the logout URL.
 *
 * @author Jeremy Mikola <jmikola@gmail.com>
 */
class LogoutUrlHelper extends Helper
{
    private $requestStack;
    private $listeners = array();
    private $router;
    private $tokenStorage;

    /**
     * Constructor.
     *
     * @param ContainerInterface|RequestStack $requestStack A ContainerInterface instance or RequestStack
     * @param UrlGeneratorInterface           $router       The router service
     * @param TokenStorageInterface|null      $tokenStorage The token storage service
     *
     * @deprecated Passing a ContainerInterface as a first argument is deprecated since 2.7 and will be removed in 3.0.
     */
    public function __construct($requestStack, UrlGeneratorInterface $router, TokenStorageInterface $tokenStorage = null)
    {
        if ($requestStack instanceof ContainerInterface) {
            $this->requestStack = $requestStack->get('request_stack');
            trigger_error('The '.__CLASS__.' constructor will require a RequestStack instead of a containerInterface instance in 3.0.', E_USER_DEPRECATED);
        } elseif ($requestStack instanceof RequestStack) {
            $this->requestStack = $requestStack;
        } else {
            throw new \InvalidArgumentException(sprintf('%s takes either a RequestStack or a ContainerInterface object as its first argument.', __METHOD__));
        }

        $this->router = $router;
        $this->tokenStorage = $tokenStorage;
    }

    /**
     * Registers a firewall's LogoutListener, allowing its URL to be generated.
     *
     * @param string                    $key              The firewall key
     * @param string                    $logoutPath       The path that starts the logout process
     * @param string                    $csrfTokenId      The ID of the CSRF token
     * @param string                    $csrfParameter    The CSRF token parameter name
     * @param CsrfTokenManagerInterface $csrfTokenManager A CsrfTokenManagerInterface instance
     */
    public function registerListener($key, $logoutPath, $csrfTokenId, $csrfParameter, $csrfTokenManager = null)
    {
        if ($csrfTokenManager instanceof CsrfProviderInterface) {
            $csrfTokenManager = new CsrfProviderAdapter($csrfTokenManager);
        } elseif (null !== $csrfTokenManager && !$csrfTokenManager instanceof CsrfTokenManagerInterface) {
            throw new \InvalidArgumentException('The CSRF token manager should be an instance of CsrfProviderInterface or CsrfTokenManagerInterface.');
        }

        $this->listeners[$key] = array($logoutPath, $csrfTokenId, $csrfParameter, $csrfTokenManager);
    }

    /**
     * Generates the absolute logout path for the firewall.
     *
     * @param string|null $key The firewall key or null to use the current firewall key
     *
     * @return string The logout path
     */
    public function getLogoutPath($key)
    {
        return $this->generateLogoutUrl($key, UrlGeneratorInterface::ABSOLUTE_PATH);
    }

    /**
     * Generates the absolute logout URL for the firewall.
     *
     * @param string|null $key The firewall key or null to use the current firewall key
     *
     * @return string The logout URL
     */
    public function getLogoutUrl($key)
    {
        return $this->generateLogoutUrl($key, UrlGeneratorInterface::ABSOLUTE_URL);
    }

    /**
     * Generates the logout URL for the firewall.
     *
     * @param string|null $key           The firewall key or null to use the current firewall key
     * @param bool|string $referenceType The type of reference (one of the constants in UrlGeneratorInterface)
     *
     * @return string The logout URL
     *
     * @throws \InvalidArgumentException if no LogoutListener is registered for the key or the key could not be found automatically.
     */
    private function generateLogoutUrl($key, $referenceType)
    {
        // Fetch the current provider key from token, if possible
        if (null === $key && null !== $this->tokenStorage) {
            $token = $this->tokenStorage->getToken();
            if (null !== $token && method_exists($token, 'getProviderKey')) {
                $key = $token->getProviderKey();
            }
        }

        if (null === $key) {
            throw new \InvalidArgumentException('Unable to find the current firewall LogoutListener, please provide the provider key manually.');
        }

        if (!array_key_exists($key, $this->listeners)) {
            throw new \InvalidArgumentException(sprintf('No LogoutListener found for firewall key "%s".', $key));
        }

        list($logoutPath, $csrfTokenId, $csrfParameter, $csrfTokenManager) = $this->listeners[$key];

        $parameters = null !== $csrfTokenManager ? array($csrfParameter => (string) $csrfTokenManager->getToken($csrfTokenId)) : array();

        if ('/' === $logoutPath[0]) {
            $request = $this->requestStack->getCurrentRequest();

            $url = UrlGeneratorInterface::ABSOLUTE_URL === $referenceType ? $request->getUriForPath($logoutPath) : $request->getBasePath().$logoutPath;

            if (!empty($parameters)) {
                $url .= '?'.http_build_query($parameters);
            }
        } else {
            $url = $this->router->generate($logoutPath, $parameters, $referenceType);
        }

        return $url;
    }

    /**
     * Returns the canonical name of this helper.
     *
     * @return string The canonical name
     */
    public function getName()
    {
        return 'logout_url';
    }
}
