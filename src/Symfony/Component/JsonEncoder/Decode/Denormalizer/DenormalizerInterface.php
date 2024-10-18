<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonEncoder\Decode\Denormalizer;

use Symfony\Component\TypeInfo\Type;

/**
 * Denormalizes data during the decoding process.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @experimental
 */
interface DenormalizerInterface
{
    /**
     * @param array<string, mixed> $options
     */
    public function denormalize(mixed $normalized, array $options = []): mixed;

    public static function getNormalizedType(): Type;
}
