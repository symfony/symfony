<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Csrf\TokenStorage;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\RuntimeException;
use Symfony\Component\Security\Core\Exception\UnexpectedValueException;

/**
 * Forwards token storage calls to a token storage stored in the master
 * request's attributes. If the attributes don't hold a token storage yet, one
 * is created and set into the attributes.
 *
 * @author Oliver Hoff <oliver@hofff.com>
 */
class RequestStackTokenStorage extends AbstractTokenStorageProxy
{
    /**
     * @var string
     */
    const DEFAULT_TOKEN_STORAGE_KEY = '_csrf_token_storage';

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var TokenStorageFactoryInterface
     */
    private $factory;

    /**
     * @var string
     */
    private $tokenStorageKey;

    /**
     * @param RequestStack $requestStack
     * @param TokenStorageFactoryInterface $factory
     * @param string|null $tokenStorageKey
     */
    public function __construct(
        RequestStack $requestStack,
        TokenStorageFactoryInterface $factory,
        $tokenStorageKey = null
    ) {
        $this->requestStack = $requestStack;
        $this->factory = $factory;
        $this->tokenStorageKey = $tokenStorageKey === null ? self::DEFAULT_TOKEN_STORAGE_KEY : $tokenStorageKey;
    }

    /**
     * {@inheritDoc}
     * @see \Symfony\Component\Security\Csrf\TokenStorage\AbstractTokenStorageProxy::getProxiedTokenStorage()
     */
    public function getProxiedTokenStorage()
    {
        // TODO use master or current request?
        $request = $this->requestStack->getMasterRequest();

        if (!$request) {
            throw new RuntimeException('Not in a request context');
        }

        $storage = $request->attributes->get($this->tokenStorageKey);

        if ($storage instanceof TokenStorageInterface) {
            return $storage;
        }

        if ($storage !== null) {
            throw new UnexpectedValueException(sprintf(
                'Expected null or an instance of "Symfony\\Component\\Security\\Csrf\\TokenStorage\\TokenStorageInterface", got "%s"',
                is_object($storage) ? get_class($storage) : gettype($storage)
            ));
        }

        $storage = $this->factory->createTokenStorage($request);
        $request->attributes->set($this->tokenStorageKey, $storage);

        return $storage;
    }

}
