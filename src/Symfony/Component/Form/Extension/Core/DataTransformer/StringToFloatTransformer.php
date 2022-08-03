<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Core\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

class StringToFloatTransformer implements DataTransformerInterface
{
    private ?int $scale;

    public function __construct(int $scale = null)
    {
        $this->scale = $scale;
    }

    public function transform(mixed $value): ?float
    {
        if (null === $value) {
            return null;
        }

        if (!\is_string($value) || !is_numeric($value)) {
            throw new TransformationFailedException('Expected a numeric string.');
        }

        return (float) $value;
    }

    public function reverseTransform(mixed $value): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!\is_int($value) && !\is_float($value)) {
            throw new TransformationFailedException('Expected a numeric.');
        }

        if ($this->scale > 0) {
            return number_format((float) $value, $this->scale, '.', '');
        }

        return (string) $value;
    }
}
