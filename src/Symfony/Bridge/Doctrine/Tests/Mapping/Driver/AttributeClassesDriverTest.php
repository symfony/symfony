<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\Mapping\Driver;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\RuntimeReflectionService;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\Mapping\Driver\AttributeClassesDriver;
use Symfony\Bridge\Doctrine\Tests\Fixtures\AssociationEntity;
use Symfony\Bridge\Doctrine\Tests\Fixtures\SingleIntIdEntity;

class AttributeClassesDriverTest extends TestCase
{
    public function testGetAllClassNames()
    {
        $driver = new AttributeClassesDriver([SingleIntIdEntity::class, AssociationEntity::class]);

        $this->assertSame([SingleIntIdEntity::class, AssociationEntity::class], $driver->getAllClassNames());
    }

    public function testGetAllClassNamesDefaultsToAnEmptyList()
    {
        $this->assertSame([], (new AttributeClassesDriver())->getAllClassNames());
    }

    public function testLoadMetadataForClass()
    {
        $metadata = new ClassMetadata(SingleIntIdEntity::class);
        $metadata->initializeReflection(new RuntimeReflectionService());

        (new AttributeClassesDriver([SingleIntIdEntity::class]))->loadMetadataForClass(SingleIntIdEntity::class, $metadata);

        $this->assertSame(['id'], $metadata->getIdentifier());
        $this->assertArrayHasKey('name', $metadata->fieldMappings);
    }
}
