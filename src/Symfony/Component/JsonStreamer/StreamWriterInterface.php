<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonStreamer;

use Symfony\Component\TypeInfo\Type;

/**
 * Writes $data into a specific format according to $options.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @experimental
 *
 * @template T of array<string, mixed>
 */
interface StreamWriterInterface
{
    /**
     * @param T $options
     *
     * @return \Traversable<int, string>&\Stringable
     */
    public function write(mixed $data, Type $type, array $options = []): \Traversable&\Stringable;
}
