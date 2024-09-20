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
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;

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
     * @param bool|array<string,mixed>|null $traverse Whether to traverse the given object or not (defaults to true). Pass an associative array to configure the constraint's options (e.g. payload).
     */
    public function __construct(bool|array|null $traverse = null)
    {
        if (\is_array($traverse) && \array_key_exists('groups', $traverse)) {
            throw new ConstraintDefinitionException(\sprintf('The option "groups" is not supported by the constraint "%s".', __CLASS__));
        }

        parent::__construct($traverse);
    }

    public function getDefaultOption(): ?string
    {
        return 'traverse';
    }

    public function getTargets(): string|array
    {
        return self::CLASS_CONSTRAINT;
    }
}
