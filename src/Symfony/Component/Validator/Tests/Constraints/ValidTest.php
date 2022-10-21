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
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\AnnotationLoader;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ValidTest extends TestCase
{
    public function testGroupsCanBeSet()
    {
        $constraint = new Valid(['groups' => 'foo']);

        $this->assertSame(['foo'], $constraint->groups);
    }

    public function testGroupsAreNullByDefault()
    {
        $constraint = new Valid();

        $this->assertNull($constraint->groups);
    }

    public function testAttributes()
    {
        $metadata = new ClassMetaData(ValidDummy::class);
        $loader = new AnnotationLoader();
        self::assertTrue($loader->loadClassMetadata($metadata));

        [$bConstraint] = $metadata->properties['b']->getConstraints();
        self::assertFalse($bConstraint->traverse);
        self::assertSame(['traverse_group'], $bConstraint->groups);

        [$cConstraint] = $metadata->properties['c']->getConstraints();
        self::assertSame(['my_group'], $cConstraint->groups);
        self::assertSame('some attached data', $cConstraint->payload);
    }
}

class ValidDummy
{
    #[Valid]
    private $a;

    #[Valid(groups: ['traverse_group'], traverse: false)] // Needs a group to work at all for this test
    private $b;

    #[Valid(groups: ['my_group'], payload: 'some attached data')]
    private $c;
}
