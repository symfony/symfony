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
 * @Target({"CLASS"})
 *
 * @author Jules Pietri <jules@heahprod.com>
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class Cascade extends Constraint
{
    public function __construct(array $options = null)
    {
        if (\is_array($options) && \array_key_exists('groups', $options)) {
            throw new ConstraintDefinitionException(sprintf('The option "groups" is not supported by the constraint "%s".', __CLASS__));
        }

        parent::__construct($options);
    }

    /**
     * {@inheritdoc}
     */
    public function getTargets()
    {
        return self::CLASS_CONSTRAINT;
    }
}
