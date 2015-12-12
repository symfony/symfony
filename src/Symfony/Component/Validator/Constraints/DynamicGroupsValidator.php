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

use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * @Annotation
 * @Target({"CLASS", "ANNOTATION"})
 */
class DynamicGroupsValidator extends ConstraintValidator
{
    /**
     * @var PropertyAccessor
     */
    private $propertyAccessor;

    public function __construct()
    {
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
    }

    /**
     * @param object|array|null $value
     * @param Constraint        $constraint
     */
    public function validate($value, Constraint $constraint)
    {
        if (null === $value) {
            return;
        }

        /** @var DynamicValidationGroup $constraint */
        $groups = $this->propertyAccessor->getValue($value, $constraint->groupProvider);

        if (is_array($groups)) {
            $this->context
                ->getValidator()
                ->inContext($this->context)
                ->atPath('')
                ->validate($value, null, $groups);
        }
    }
}
