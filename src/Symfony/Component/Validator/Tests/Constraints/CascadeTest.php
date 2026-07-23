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
use Symfony\Component\Validator\Constraints\Cascade;
use Symfony\Component\Validator\Mapping\CascadingStrategy;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\AttributeLoader;

class CascadeTest extends TestCase
{
    public function testCascadeAttribute()
    {
        $metadata = new ClassMetadata(CascadeDummy::class);
        $loader = new AttributeLoader();
        self::assertSame(CascadingStrategy::NONE, $metadata->getCascadingStrategy());
        self::assertTrue($loader->loadClassMetadata($metadata));
        self::assertSame(CascadingStrategy::CASCADE, $metadata->getCascadingStrategy());
    }

    public function testExcludeProperties()
    {
        $constraint = new Cascade(['foo', 'bar']);

        self::assertSame(['foo' => 0, 'bar' => 1], $constraint->exclude);
    }
}

#[Cascade]
class CascadeDummy
{
}
