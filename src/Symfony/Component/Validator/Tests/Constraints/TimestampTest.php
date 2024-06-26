<?php

namespace Symfony\Component\Validator\Tests\Constraints;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints\Timestamp;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\AttributeLoader;

class TimestampTest extends TestCase
{
    public function testAttributes()
    {
        $metadata = new ClassMetadata(TimestampDummy::class);
        $loader = new AttributeLoader();
        self::assertTrue($loader->loadClassMetadata($metadata));

        [$aConstraint] = $metadata->properties['a']->getConstraints();
        self::assertSame('+1 month', $aConstraint->greaterThan);
    }
}

class TimestampDummy
{
    #[Timestamp(greaterThan: '+1 month')]
    private $a;
}