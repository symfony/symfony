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

/**
 * @author Yonel Ceruto <open@yceruto.dev>
 */
final class NullDataStorage implements DataStorageInterface
{
    public function save(object|array $data): void
    {
        // no-op
    }

    public function load(object|array|null $default = null): object|array|null
    {
        return $default;
    }

    public function clear(): void
    {
        // no-op
    }
}
