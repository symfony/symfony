<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * Validates an object that needs to be traversed.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class Traverse extends Constraint
{
    public bool $traverse = true;

    /**
     * @param bool|null $traverse Whether to traverse the given object or not (defaults to true). Pass an associative array to configure the constraint's options (e.g. payload).
     */
    public function __construct(?bool $traverse = null, mixed $payload = null)
    {
        parent::__construct(null, $payload);

        $this->traverse = $traverse ?? $this->traverse;
    }

    public function getTargets(): string|array
    {
        return self::CLASS_CONSTRAINT;
    }
}
