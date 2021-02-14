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

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 *
 * @internal to be removed in Symfony 6
 */
final class ServiceSessionFactory implements SessionStorageFactoryInterface
{
    private $storage;

    public function __construct(SessionStorageInterface $storage)
    {
        $this->storage = $storage;
    }

    public function createStorage(?Request $request): SessionStorageInterface
    {
        if ($this->storage instanceof NativeSessionStorage && $request && $request->isSecure()) {
            $this->storage->setOptions(['cookie_secure' => true]);
        }

        return $this->storage;
    }
}
