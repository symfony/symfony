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

        public function testEmbeddedGroupsAreUsedForChildValidation()
    {
        $validatorBuilder = new ValidatorBuilder();
        $validator = $validatorBuilder->enableAttributeMapping()->getValidator();

        $violations = $validator->validate(new ParentWithEmbeddedGroups(), null, ['children']);

        $this->assertCount(2, $violations);
        $this->assertSame('child.name', $violations->get(0)->getPropertyPath());
        $this->assertSame('child.code', $violations->get(1)->getPropertyPath());
    }

    public function testDefaultBehaviorPreservedWhenEmbeddedGroupsNotSet()
    {
        $validatorBuilder = new ValidatorBuilder();
        $validator = $validatorBuilder->enableAttributeMapping()->getValidator();

        $violations = $validator->validate(new ParentWithoutEmbeddedGroups(), null, ['children']);

        $this->assertCount(0, $violations);
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

class ChildWithGroups
{
    #[Assert\NotBlank]
    public ?string $name = null;

    #[Assert\Length(max: 3, groups: ['strict'])]
    public string $code = 'toolong';
}

class ParentWithEmbeddedGroups
{
    #[Assert\Valid(groups: ['children'], embeddedGroups: ['Default', 'strict'])]
    public ChildWithGroups $child;

    public function __construct()
    {
        $this->child = new ChildWithGroups();
    }
}

class ParentWithoutEmbeddedGroups
{
    #[Assert\Valid(groups: ['children'])]
    public ChildWithGroups $child;

    public function __construct()
    {
        $this->child = new ChildWithGroups();
    }
}
