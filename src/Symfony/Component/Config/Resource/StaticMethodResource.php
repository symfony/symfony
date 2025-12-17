<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Resource;

class StaticMethodResource implements SelfCheckingResourceInterface
{
    private string $hash;

    /**
     * @param array $staticMethod A callable represented as [className, methodName]
     */
    public function __construct(
        private $staticMethod,
    ) {
    }

    public function isFresh(int $timestamp): bool
    {
        $hash = $this->computeHash();
        $this->hash ??= $hash;

        return $this->hash === $hash;
    }

    public function __serialize(): array
    {
        $this->hash ??= $this->computeHash();

        return [
            'hash' => $this->hash,
            'staticMethod' => $this->staticMethod,
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->hash = array_shift($data);
        $this->staticMethod = array_shift($data);
    }

    public function __toString(): string
    {
        return 'static_method_resource'.$this->staticMethod[0].'::'.$this->staticMethod[1];
    }

    private function computeHash(): string
    {
        $hash = hash_init('xxh128');

        $ret = ($this->staticMethod)();

        if (is_iterable($ret)) {
            foreach ($ret as $value) {
                hash_update($hash, serialize($value));
            }
        } else {
            hash_update($hash, serialize($ret));
        }

        return hash_final($hash);
    }
}
