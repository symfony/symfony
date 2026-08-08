<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Impersonate;

use Symfony\Bundle\SecurityBundle\Security\FirewallMap;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Firewall\SwitchUserListener;

/**
 * Provides generator functions for the impersonation urls.
 *
 * @author Amrouche Hamza <hamza.simperfit@gmail.com>
 * @author Damien Fayet <damienf1521@gmail.com>
 */
class ImpersonateUrlGenerator
{
    public function __construct(
        private RequestStack $requestStack,
        private FirewallMap $firewallMap,
        private TokenStorageInterface $tokenStorage,
        private ?UrlGeneratorInterface $urlGenerator = null,
        private ?CsrfTokenManagerInterface $csrfTokenManager = null,
    ) {
    }

    public function generateImpersonationPath(string $identifier/* , ?string $targetUri = null */): string
    {
        $targetUri = 1 < \func_num_args() ? func_get_arg(1) : null;

        return $this->buildUrl(UrlGeneratorInterface::ABSOLUTE_PATH, $targetUri, $identifier);
    }

    public function generateImpersonationUrl(string $identifier/* , ?string $targetUri = null */): string
    {
        $targetUri = 1 < \func_num_args() ? func_get_arg(1) : null;

        return $this->buildUrl(UrlGeneratorInterface::ABSOLUTE_URL, $targetUri, $identifier);
    }

    public function generateExitPath(?string $targetUri = null): string
    {
        return $this->buildUrl(UrlGeneratorInterface::ABSOLUTE_PATH, $targetUri);
    }

    public function generateExitUrl(?string $targetUri = null): string
    {
        return $this->buildUrl(UrlGeneratorInterface::ABSOLUTE_URL, $targetUri);
    }

    /**
     * Returns the action and the hidden fields of a form triggering the impersonation.
     *
     * Unlike the URLs, this keeps the parameters out of the address bar and works with
     * a path restricted to POST requests.
     *
     * @return array{action: string, fields: array<string, string>}
     */
    public function generateImpersonationForm(string $identifier, ?string $targetUri = null): array
    {
        return $this->buildForm($targetUri, $identifier);
    }

    /**
     * Returns the action and the hidden fields of a form exiting the impersonation.
     *
     * @return array{action: string, fields: array<string, string>}
     */
    public function generateExitForm(?string $targetUri = null): array
    {
        return $this->buildForm($targetUri);
    }

    private function isImpersonatedUser(): bool
    {
        return $this->tokenStorage->getToken() instanceof SwitchUserToken;
    }

    private function buildUrl(int $referenceType, ?string $targetUri = null, string $identifier = SwitchUserListener::EXIT_VALUE): string
    {
        [$url, $parameters] = $this->buildParts($referenceType, $targetUri, $identifier);

        if ('' === $url || !$parameters) {
            return $url;
        }

        return $url.(str_contains($url, '?') ? '&' : '?').http_build_query($parameters, '', '&');
    }

    /**
     * @return array{action: string, fields: array<string, string>}
     */
    private function buildForm(?string $targetUri = null, string $identifier = SwitchUserListener::EXIT_VALUE): array
    {
        [$action, $fields] = $this->buildParts(UrlGeneratorInterface::ABSOLUTE_PATH, $targetUri, $identifier);

        return ['action' => $action, 'fields' => $fields];
    }

    /**
     * @return array{0: string, 1: array<string, string>}
     */
    private function buildParts(int $referenceType, ?string $targetUri, string $identifier): array
    {
        if (null === ($request = $this->requestStack->getCurrentRequest())) {
            return ['', []];
        }

        if (!$this->isImpersonatedUser() && SwitchUserListener::EXIT_VALUE == $identifier) {
            return ['', []];
        }

        if (null === $switchUserConfig = $this->firewallMap->getFirewallConfig($request)?->getSwitchUser()) {
            throw new \LogicException('Unable to generate the impersonate URLs without a firewall configured for the user switch.');
        }

        $parameters = [$switchUserConfig['parameter'] => $identifier];

        if ($switchUserConfig['enable_csrf'] ?? false) {
            if (null === $this->csrfTokenManager) {
                throw new \LogicException('Unable to generate the impersonate URLs without a CSRF token manager.');
            }

            // the token id is served by the manager the firewall configured for it
            $parameters[$switchUserConfig['csrf_parameter']] = $this->csrfTokenManager->getToken($switchUserConfig['csrf_token_id'])->getValue();
        }

        if (null !== $path = $switchUserConfig['path'] ?? null) {
            // the switch happens on a dedicated endpoint, so the page to come back to is carried explicitly
            $parameters['_target_path'] = $targetUri ?? $request->getRequestUri();

            if ('/' !== $path[0]) {
                if (null === $this->urlGenerator) {
                    throw new \LogicException('Unable to generate the impersonate URLs from a route name without a URL generator.');
                }

                // the route may take some of the parameters as placeholders, only the others are left over
                return self::splitQuery($this->urlGenerator->generate($path, $parameters, $referenceType));
            }

            $url = UrlGeneratorInterface::ABSOLUTE_URL === $referenceType ? $request->getUriForPath($path) : $request->getBaseUrl().$path;
        } else {
            $url = $targetUri ?? $request->getRequestUri();

            if (UrlGeneratorInterface::ABSOLUTE_URL === $referenceType) {
                $url = $request->getUriForPath($url);
            }
        }

        return [$url, $parameters];
    }

    /**
     * @return array{0: string, 1: array<string, string>}
     */
    private static function splitQuery(string $url): array
    {
        [$url, $query] = explode('?', $url, 2) + ['', ''];
        $parameters = [];

        // parse_str() cannot be used here as it turns dots and spaces in parameter names into underscores
        foreach ('' === $query ? [] : explode('&', $query) as $pair) {
            [$name, $value] = explode('=', $pair, 2) + ['', ''];
            $parameters[urldecode($name)] = urldecode($value);
        }

        return [$url, $parameters];
    }
}
