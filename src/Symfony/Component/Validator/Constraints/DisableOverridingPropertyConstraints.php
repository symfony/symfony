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
 * Disables overriding property constraints of parent class.
 *
 * Using the annotations on a property has higher precedence than using it on a class.
 *
 * @Annotation
 *
 * @author Przemysław Bogusz <przemyslaw.bogusz@tubotax.pl>
 */
class DisableOverridingPropertyConstraints extends Constraint
{
    public function __construct($options = null)
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
        return [self::PROPERTY_CONSTRAINT, self::CLASS_CONSTRAINT];
    }
}
