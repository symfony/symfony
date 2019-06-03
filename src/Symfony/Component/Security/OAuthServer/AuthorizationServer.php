<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\OAuthServer;

use Psr\Log\LoggerInterface;
use Symfony\Component\Security\OAuthServer\Event\EndAuthorizationRequestHandlingEvent;
use Symfony\Component\Security\OAuthServer\Event\StartAuthorizationRequestHandlingEvent;
use Symfony\Component\Security\OAuth\Exception\InvalidRequestException;
use Symfony\Component\Security\OAuthServer\Exception\MissingGrantTypeException;
use Symfony\Component\Security\OAuthServer\Exception\UnhandledRequestException;
use Symfony\Component\Security\OAuthServer\GrantTypes\GrantTypeInterface;
use Symfony\Component\Security\OAuthServer\Request\AuthorizationRequest;
use Symfony\Component\Security\OAuthServer\Response\AbstractResponse;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class AuthorizationServer implements AuthorizationServerInterface
{
    /**
     * @var GrantTypeInterface[]
     */
    private $grantTypes = [];
    private $logger;
    private $eventDispatcher;

    public function __construct(array $grantTypes = [], EventDispatcherInterface $eventDispatcher = null, LoggerInterface $logger = null)
    {
        $this->grantTypes = $grantTypes;
        $this->logger = $logger;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * Handle a request.
     *
     * @param object|null $request
     *
     * @return AbstractResponse
     */
    public function handle($request = null)
    {
        if (0 === \count($this->grantTypes)) {
            throw new MissingGrantTypeException('At least one grant type should be passed!');
        }

        $authorizationRequest = AuthorizationRequest::create($request);

        if ($this->eventDispatcher) {
            $this->eventDispatcher->dispatch(new StartAuthorizationRequestHandlingEvent($authorizationRequest));
        }

        $response = null;

        if (null !== $request->getValue('response_type')) {
            $response = $this->handleAuthorizationRequest($request);
        }

        if (null !== $request->getValue('grant_type')) {
            $response = $this->handleAccessTokenRequest($request);
        }

        if (null === $response) {
            throw new UnhandledRequestException('');
        }

        if ($this->eventDispatcher) {
            $this->eventDispatcher->dispatch(new EndAuthorizationRequestHandlingEvent($request, $response));
        }

        return $response;
    }

    private function handleAuthorizationRequest($request = null)
    {
        foreach ($this->grantTypes as $grantType) {
            if (!$grantType->canHandleAuthorizationRequest($request)) {
                continue;
            }

            $grantType->handleAuthorizationRequest($request);
        }

        throw new InvalidRequestException('');
    }

    private function handleAccessTokenRequest($request = null)
    {
        foreach ($this->grantTypes as $grantType) {
            if (!$grantType->canHandleAccessTokenRequest($request)) {
                continue;
            }

            $grantType->handleAccessTokenRequest($request);
        }

        throw new InvalidRequestException('');
    }
}
