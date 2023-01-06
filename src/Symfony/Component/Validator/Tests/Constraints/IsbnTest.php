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
use Symfony\Component\Validator\Constraints\Isbn;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\AnnotationLoader;

class IsbnTest extends TestCase
{
    public function testAttributes()
    {
        $metadata = new ClassMetadata(IsbnDummy::class);
        $loader = new AnnotationLoader();
        self::assertTrue($loader->loadClassMetadata($metadata));

        [$aConstraint] = $metadata->properties['a']->getConstraints();
        self::assertNull($aConstraint->type);

        [$bConstraint] = $metadata->properties['b']->getConstraints();
        self::assertSame(Isbn::ISBN_13, $bConstraint->type);
        self::assertSame('myMessage', $bConstraint->message);
        self::assertSame(['Default', 'IsbnDummy'], $bConstraint->groups);

        [$cConstraint] = $metadata->properties['c']->getConstraints();
        self::assertSame(['my_group'], $cConstraint->groups);
        self::assertSame('some attached data', $cConstraint->payload);
    }
}

class IsbnDummy
{
    #[Isbn]
    private $a;

    #[Isbn(message: 'myMessage', type: Isbn::ISBN_13)]
    private $b;

    #[Isbn(groups: ['my_group'], payload: 'some attached data')]
    private $c;
}
