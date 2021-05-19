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
use Symfony\Component\Validator\Constraints\Issn;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\AnnotationLoader;

class IssnTest extends TestCase
{
    public function testAttributes()
    {
        $metadata = new ClassMetadata(IssnDummy::class);
        $loader = new AnnotationLoader();
        self::assertTrue($loader->loadClassMetadata($metadata));

        [$aConstraint] = $metadata->properties['a']->getConstraints();
        self::assertFalse($aConstraint->caseSensitive);
        self::assertFalse($aConstraint->requireHyphen);

        [$bConstraint] = $metadata->properties['b']->getConstraints();
        self::assertSame('myMessage', $bConstraint->message);
        self::assertTrue($bConstraint->caseSensitive);
        self::assertTrue($bConstraint->requireHyphen);
        self::assertSame(['Default', 'IssnDummy'], $bConstraint->groups);

        [$cConstraint] = $metadata->properties['c']->getConstraints();
        self::assertSame(['my_group'], $cConstraint->groups);
        self::assertSame('some attached data', $cConstraint->payload);
    }
}

class IssnDummy
{
    #[Issn]
    private $a;

    #[Issn(message: 'myMessage', caseSensitive: true, requireHyphen: true)]
    private $b;

    #[Issn(groups: ['my_group'], payload: 'some attached data')]
    private $c;
}
