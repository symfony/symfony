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
use Symfony\Component\Validator\Constraints\EnableAutoMapping;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Mapping\AutoMappingStrategy;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\AnnotationLoader;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class EnableAutoMappingTest extends TestCase
{
    public function testGroups()
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessage(sprintf('The option "groups" is not supported by the constraint "%s".', EnableAutoMapping::class));

        new EnableAutoMapping(['groups' => 'foo']);
    }

    public function testDisableAutoMappingAttribute()
    {
        $metadata = new ClassMetadata(EnableAutoMappingDummy::class);
        $loader = new AnnotationLoader();
        self::assertSame(AutoMappingStrategy::NONE, $metadata->getAutoMappingStrategy());
        self::assertTrue($loader->loadClassMetadata($metadata));
        self::assertSame(AutoMappingStrategy::ENABLED, $metadata->getAutoMappingStrategy());
    }
}

#[EnableAutoMapping]
class EnableAutoMappingDummy
{
}
