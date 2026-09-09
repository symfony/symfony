<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Recorder\Store;

use Symfony\Component\HttpClient\Har\HarFile;

interface StoreInterface
{
    /**
     * Loads the file, hands it to the callback and writes the result back.
     *
     * Implementations must make the whole cycle atomic: concurrent recorders must not lose entries, and
     * an interrupted run must not leave a partially written file behind.
     *
     * @param callable(HarFile):void $mutate
     */
    public function update(string $name, callable $mutate): void;
}
