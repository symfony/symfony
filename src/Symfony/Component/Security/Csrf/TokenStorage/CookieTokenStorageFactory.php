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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\InvalidArgumentException;

/**
 * Creates CSRF token storages based on the requests cookies.
 *
 * @author Oliver Hoff <oliver@hofff.com>
 */
class CookieTokenStorageFactory implements TokenStorageFactoryInterface
{
    /**
     * @var string
     */
    private $secret;

    /**
     * @var int
     */
    private $ttl;

    /**
     * @param string $secret
     * @param int    $ttl
     */
    public function __construct($secret, $ttl = null)
    {
        $this->secret = (string) $secret;
        $this->ttl = $ttl === null ? 60 * 60 : (int) $ttl;

        if ('' === $this->secret) {
            throw new InvalidArgumentException('Secret must be a non-empty string');
        }

        if ($this->ttl < 60) {
            throw new InvalidArgumentException('TTL must be an integer greater than or equal to 60');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function createTokenStorage(Request $request)
    {
        return new CookieTokenStorage($request->headers->get('Cookie'), $request->isSecure(), $this->secret, $this->ttl);
    }
}
