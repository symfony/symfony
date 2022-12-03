<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Tests\Extractor;

use Doctrine\Common\Annotations\AnnotationReader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyInfo\Extractor\SerializerExtractor;
use Symfony\Component\PropertyInfo\Tests\Fixtures\AdderRemoverDummy;
use Symfony\Component\PropertyInfo\Tests\Fixtures\IgnorePropertyDummy;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AnnotationLoader;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class SerializerExtractorTest extends TestCase
{
    /**
     * @var SerializerExtractor
     */
    private $extractor;

    protected function setUp(): void
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $this->extractor = new SerializerExtractor($classMetadataFactory);
    }

    public function testGetProperties()
    {
        $this->assertEquals(
            ['collection'],
            $this->extractor->getProperties('Symfony\Component\PropertyInfo\Tests\Fixtures\Dummy', ['serializer_groups' => ['a']])
        );
    }

    public function testGetPropertiesWithIgnoredProperties()
    {
        $this->assertSame(['visibleProperty'], $this->extractor->getProperties(IgnorePropertyDummy::class, ['serializer_groups' => ['a']]));
    }

    public function testGetPropertiesWithAnyGroup()
    {
        $this->assertSame(['analyses', 'feet'], $this->extractor->getProperties(AdderRemoverDummy::class, ['serializer_groups' => null]));
    }
}
