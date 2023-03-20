<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonEncoder\Stream;

/**
 * Reads stream data sequentially.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @experimental
 *
 * @extends \IteratorAggregate<string>
 */
interface StreamReaderInterface extends \IteratorAggregate, \Stringable
{
    public function read(?int $length = null): string;

    public function seek(int $offset): void;

    public function rewind(): void;
}
