<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\Attribute;

/**
 * Defines metadata for a place represented by an enum case.
 *
 * @author Antonio Pauletich <antonio.pauletich95@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_CLASS_CONSTANT)]
final class Place
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(public readonly array $metadata = [])
    {
    }
}
