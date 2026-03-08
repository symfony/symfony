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
use Symfony\Component\Validator\Constraints\Sum;
use Symfony\Component\Validator\Exception\MissingOptionsException;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\AttributeLoader;

class SumTest extends TestCase
{
    public function testAttributes()
    {
        $metadata = new ClassMetadata(SumDummy::class);
        $loader = new AttributeLoader();
        $this->assertTrue($loader->loadClassMetadata($metadata));

        [$aConstraint] = $metadata->getPropertyMetadata('a')[0]->getConstraints();
        $this->assertSame(42.0, $aConstraint->min);
        $this->assertSame(42.0, $aConstraint->max);
        $this->assertSame('myExactMessage', $aConstraint->exactMessage);

        [$bConstraint] = $metadata->getPropertyMetadata('b')[0]->getConstraints();
        $this->assertSame(1.0, $bConstraint->min);
        $this->assertSame(4711.0, $bConstraint->max);
        $this->assertSame('myMinMessage', $bConstraint->minMessage);
        $this->assertSame('myMaxMessage', $bConstraint->maxMessage);
        $this->assertSame(['Default', 'SumDummy'], $bConstraint->groups);

        [$cConstraint] = $metadata->getPropertyMetadata('c')[0]->getConstraints();
        $this->assertSame(10.0, $cConstraint->min);
        $this->assertSame(10.0, $cConstraint->max);
        $this->assertSame(['my_group'], $cConstraint->groups);
        $this->assertSame('some attached data', $cConstraint->payload);
    }

    public function testMissingOptions()
    {
        $this->expectException(MissingOptionsException::class);
        $this->expectExceptionMessage(\sprintf('Either option "min" or "max" must be given for constraint "%s".', Sum::class));

        new Sum();
    }
}

class SumDummy
{
    #[Sum(exactly: 42, exactMessage: 'myExactMessage')]
    private $a;

    #[Sum(min: 1, max: 4711, minMessage: 'myMinMessage', maxMessage: 'myMaxMessage')]
    private $b;

    #[Sum(exactly: 10, groups: ['my_group'], payload: 'some attached data')]
    private $c;
}
