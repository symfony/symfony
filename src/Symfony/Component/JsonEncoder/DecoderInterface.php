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

use Symfony\Component\TypeInfo\Type;

/**
 * Decodes an $input into a given $type according to $options.
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
     * @param resource|string $input
     * @param T               $options
     */
    public function decode($input, Type $type, array $options = []): mixed;
}
