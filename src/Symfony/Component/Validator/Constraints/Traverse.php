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
 * @Annotation
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class Traverse extends Constraint
{
    public $traverse = true;

    public function __construct(bool|array $traverse = null)
    {
        if (\is_array($traverse) && \array_key_exists('groups', $traverse)) {
            throw new ConstraintDefinitionException(sprintf('The option "groups" is not supported by the constraint "%s".', __CLASS__));
        }

        parent::__construct($traverse);
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultOption(): ?string
    {
        return 'traverse';
    }

    /**
     * {@inheritdoc}
     */
    public function getTargets(): string|array
    {
        return self::CLASS_CONSTRAINT;
    }
}
