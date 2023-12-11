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

use Symfony\Component\Validator\Constraints\EqualTo;
use Symfony\Component\Validator\Constraints\ThisableMessage;
use Symfony\Component\Validator\Constraints\ThisableMessageValidator;
use Symfony\Component\Validator\Context\ExecutionContext;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Contracts\Translation\TranslatorInterface;

class ThisableMessageValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): ThisableMessageValidator
    {
        return new ThisableMessageValidator();
    }

    public function testWalkSingleConstraint()
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects($this->any())->method('trans')->willReturnArgument(0);

        $validator = Validation::createValidator();

        $root = new ThisableMessageRoot();
        $child = new ThisableMessageChild();
        $root->childs[] = $child;

        $this->setRoot($root);
        $this->setObject($child);

        $context = new ExecutionContext($validator, $root, $translator);
        $context->setGroup($this->group);
        $context->setNode($child->val, $child, new ClassMetadata(ThisableMessageRoot::class), 'list[0].val');

        $this->root = $root;
        $this->setObject($child);

        $equal = new EqualTo(2);
        $equal->message = '{{ root.who }} and {{ this.who }}';

        $constraint = new ThisableMessage([$equal]);
        $constraint->addRootParameters = ['val2'];
        $constraint->addThisParameters = ['who2'];
        $violations = $validator->inContext($context)
            ->validate($child->val, $constraint, 'Default')
            ->getViolations();

        $parameters = $violations->get(0)->getParameters();
        $this->assertEquals('21', $parameters['{{ value }}']);
        $this->assertEquals('2', $parameters['{{ compared_value }}']);
        $this->assertEquals('int', $parameters['{{ compared_value_type }}']);

        $this->assertEquals('child', $parameters['{{ this.who }}']);
        $this->assertEquals('parent', $parameters['{{ root.who }}']);

        $this->assertEquals('child2', $parameters['{{ this.who2 }}']);
        $this->assertEquals('123', $parameters['{{ root.val2 }}']);
    }
}

class ThisableMessageRoot
{
    public string $who = 'parent';
    public int $val = 42;
    public int $val2 = 123;

    #[Valid()]
    public array $childs;
}

class ThisableMessageChild
{
    public string $who = 'child';
    public string $who2 = 'child2';

    #[ThisableMessage([
        new EqualTo(2, '{{ root.who }} {{ this.who }}'),
    ])]
    public int $val = 21;
}
