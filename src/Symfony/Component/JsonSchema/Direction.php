<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonSchema;

/**
 * Whether a schema describes the data a class is denormalized from, or the data it is normalized to.
 *
 * @experimental
 */
enum Direction
{
    /**
     * Only the writable properties, required when the class cannot be instantiated without them.
     */
    case Input;

    /**
     * Only the readable properties, required when they are always initialized.
     */
    case Output;
}
