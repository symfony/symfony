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
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\AnnotationLoader;

class TypeTest extends TestCase
{
    public function testAttributes()
    {
        $metadata = new ClassMetadata(TypeDummy::class);
        self::assertTrue((new AnnotationLoader())->loadClassMetadata($metadata));

        [$aConstraint] = $metadata->properties['a']->getConstraints();
        self::assertSame('integer', $aConstraint->type);

        [$bConstraint] = $metadata->properties['b']->getConstraints();
        self::assertSame(\DateTime::class, $bConstraint->type);
        self::assertSame('myMessage', $bConstraint->message);
        self::assertSame(['Default', 'TypeDummy'], $bConstraint->groups);

        [$cConstraint] = $metadata->properties['c']->getConstraints();
        self::assertSame(['string', 'array'], $cConstraint->type);
        self::assertSame(['my_group'], $cConstraint->groups);
        self::assertSame('some attached data', $cConstraint->payload);
    }
}

class TypeDummy
{
    #[Type('integer')]
    private $a;

    #[Type(type: \DateTime::class, message: 'myMessage')]
    private $b;

    #[Type(type: ['string', 'array'], groups: ['my_group'], payload: 'some attached data')]
    private $c;
}
