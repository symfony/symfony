<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonEncoder;

use Symfony\Component\JsonEncoder\Stream\StreamReaderInterface;
use Symfony\Component\TypeInfo\Type;

/**
 * Decodes an $input into a given $type according to a $config.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @experimental
 *
 * @template T of array<string, mixed>
 */
interface DecoderInterface
{
    /**
     * @param StreamReaderInterface|\Traversable<string>|\Stringable|string $input
     * @param T                                                             $config
     */
    public function decode(StreamReaderInterface|\Traversable|\Stringable|string $input, Type $type, array $config = []): mixed;
}
