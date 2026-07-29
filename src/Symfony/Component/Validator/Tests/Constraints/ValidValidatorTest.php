<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Constraints;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\ValidatorBuilder;

class ValidValidatorTest extends TestCase
{
    public function testPropertyPathsArePassedToNestedContexts()
    {
        $validatorBuilder = new ValidatorBuilder();
        $validator = $validatorBuilder->enableAttributeMapping()->getValidator();

        $violations = $validator->validate(new Foo(), null, ['nested']);

        $this->assertCount(1, $violations);
        $this->assertSame('fooBar.fooBarBaz.foo', $violations->get(0)->getPropertyPath());
    }

    public function testNullValues()
    {
        $validatorBuilder = new ValidatorBuilder();
        $validator = $validatorBuilder->enableAttributeMapping()->getValidator();

        $foo = new Foo();
        $foo->fooBar = null;
        $violations = $validator->validate($foo, null, ['nested']);

        $this->assertCount(0, $violations);
    }

    public function testCascadedGroupsAreUsedForNestedValidation()
    {
        $validatorBuilder = new ValidatorBuilder();
        $validator = $validatorBuilder->enableAttributeMapping()->getValidator();

        $parent = new ParentWithCascadedGroups();
        $violations = $validator->validate($parent, null, ['trigger']);

        // Should have 2 violations: one for Default group constraint, one for extra group constraint
        $this->assertCount(2, $violations);
    }

    public function testCascadedGroupsWithoutExplicitGroups()
    {
        $validatorBuilder = new ValidatorBuilder();
        $validator = $validatorBuilder->enableAttributeMapping()->getValidator();

        // the natural spelling: no explicit "groups", validated in the Default group
        $violations = $validator->validate(new ParentWithNaturalCascadedGroups());

        $this->assertCount(2, $violations);
    }

    public function testEmptyCascadedGroupsSkipTheNestedValidation()
    {
        $validatorBuilder = new ValidatorBuilder();
        $validator = $validatorBuilder->enableAttributeMapping()->getValidator();

        $violations = $validator->validate(new ParentWithEmptyCascadedGroups(), null, ['trigger']);

        $this->assertCount(0, $violations);
    }

    public function testCascadedGroupsWithAnExplicitConstraint()
    {
        $validatorBuilder = new ValidatorBuilder();
        $validator = $validatorBuilder->enableAttributeMapping()->getValidator();

        $violations = $validator->validate(new ChildWithMultipleGroups(), new Assert\Valid(cascadedGroups: ['Default', 'extra']));

        $this->assertCount(2, $violations);
    }

    public function testCascadedGroupsDefaultBehaviorPreserved()
    {
        $validatorBuilder = new ValidatorBuilder();
        $validator = $validatorBuilder->enableAttributeMapping()->getValidator();

        // Without cascadedGroups, only the current group should be used
        $parent = new ParentWithoutCascadedGroups();
        $violations = $validator->validate($parent, null, ['trigger']);

        // Should have only 1 violation: only the 'trigger' group constraint is validated
        $this->assertCount(1, $violations);
    }
}

class Foo
{
    #[Assert\Valid(groups: ['nested'])]
    public $fooBar;

    public function __construct()
    {
        $this->fooBar = new FooBar();
    }
}

class FooBar
{
    #[Assert\Valid(groups: ['nested'])]
    public $fooBarBaz;

    public function __construct()
    {
        $this->fooBarBaz = new FooBarBaz();
    }
}

class FooBarBaz
{
    #[Assert\NotBlank(groups: ['nested'])]
    public $foo;
}

class ParentWithCascadedGroups
{
    #[Assert\Valid(groups: ['trigger'], cascadedGroups: ['Default', 'extra'])]
    public $child;

    public function __construct()
    {
        $this->child = new ChildWithMultipleGroups();
    }
}

class ParentWithNaturalCascadedGroups
{
    #[Assert\Valid(cascadedGroups: ['Default', 'extra'])]
    public $child;

    public function __construct()
    {
        $this->child = new ChildWithMultipleGroups();
    }
}

class ParentWithEmptyCascadedGroups
{
    #[Assert\Valid(groups: ['trigger'], cascadedGroups: [])]
    public $child;

    public function __construct()
    {
        $this->child = new ChildWithMultipleGroups();
    }
}

class ParentWithoutCascadedGroups
{
    #[Assert\Valid(groups: ['trigger'])]
    public $child;

    public function __construct()
    {
        $this->child = new ChildWithMultipleGroups();
    }
}

class ChildWithMultipleGroups
{
    #[Assert\NotBlank]
    public $defaultField;

    #[Assert\NotBlank(groups: ['extra'])]
    public $extraField;

    #[Assert\NotBlank(groups: ['trigger'])]
    public $triggerField;
}
