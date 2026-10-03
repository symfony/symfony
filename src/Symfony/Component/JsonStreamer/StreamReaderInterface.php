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
 * Reads a string or a stream into a PHP value of the given type.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @template T of array<string, mixed>
 */
interface StreamReaderInterface
{
    /**
     * @param resource|string $input
     * @param T               $options
     */
    public function read($input, Type $type, array $options = []): mixed;
}
