<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Session\Storage;

use Symfony\Component\HttpFoundation\Request;

// Help opcache.preload discover always-needed symbols
class_exists(PhpBridgeSessionStorage::class);

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class PhpBridgeSessionStorageFactory implements SessionStorageFactoryInterface
{
    private $handler;
    private $metaBag;
    private $secure;

    /**
     * @see PhpBridgeSessionStorage constructor.
     */
    public function __construct($handler = null, ?MetadataBag $metaBag = null, bool $secure = false)
    {
        $this->handler = $handler;
        $this->metaBag = $metaBag;
        $this->secure = $secure;
    }

    public function createStorage(?Request $request): SessionStorageInterface
    {
        $storage = new PhpBridgeSessionStorage($this->handler, $this->metaBag);
        if ($this->secure && $request && $request->isSecure()) {
            $storage->setOptions(['cookie_secure' => true]);
        }

        return $storage;
    }
}
