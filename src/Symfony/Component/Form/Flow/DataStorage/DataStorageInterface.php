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
 * Handles storing and retrieving form data between steps.
 *
 * @author Yonel Ceruto <open@yceruto.dev>
 */
interface DataStorageInterface
{
    public function save(object|array $data): void;

    public function load(object|array|null $default = null): object|array|null;

    public function clear(): void;
}
