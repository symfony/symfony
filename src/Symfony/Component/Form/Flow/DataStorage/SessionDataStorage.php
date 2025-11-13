<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Flow\DataStorage;

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @author Yonel Ceruto <open@yceruto.dev>
 */
class SessionDataStorage implements DataStorageInterface
{
    public function __construct(
        private readonly string $key,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function save(object|array $data): void
    {
        $this->requestStack->getSession()->set($this->key, $data);
    }

    public function load(object|array|null $default = null): object|array|null
    {
        return $this->requestStack->getSession()->get($this->key, $default);
    }

    public function clear(): void
    {
        $this->requestStack->getSession()->remove($this->key);
    }
}
