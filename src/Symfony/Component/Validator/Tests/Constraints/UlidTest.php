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
use Symfony\Component\Validator\Constraints\Ulid;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\AttributeLoader;

class UlidTest extends TestCase
{
    public function testAttributes()
    {
        $metadata = new ClassMetadata(UlidDummy::class);
        $loader = new AttributeLoader();
        self::assertTrue($loader->loadClassMetadata($metadata));

        [$bConstraint] = $metadata->properties['b']->getConstraints();
        self::assertSame('myMessage', $bConstraint->message);
        self::assertSame(['Default', 'UlidDummy'], $bConstraint->groups);

        [$cConstraint] = $metadata->properties['c']->getConstraints();
        self::assertSame(['my_group'], $cConstraint->groups);
        self::assertSame('some attached data', $cConstraint->payload);
    }

    public function testUnexpectedValidationFormat()
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessage('The "invalid" validation format is not supported.');

        new Ulid(format: 'invalid');
    }
}

class UlidDummy
{
    #[Ulid]
    private $a;

    #[Ulid(message: 'myMessage')]
    private $b;

    #[Ulid(groups: ['my_group'], payload: 'some attached data')]
    private $c;
}
