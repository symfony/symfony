<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\PropertyInfo\Tests\Extractors;

use Doctrine\Common\Annotations\AnnotationReader;
use PHPUnit\Framework\TestCase;
use Symphony\Component\PropertyInfo\Extractor\SerializerExtractor;
use Symphony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symphony\Component\Serializer\Mapping\Loader\AnnotationLoader;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class SerializerExtractorTest extends TestCase
{
    /**
     * @var SerializerExtractor
     */
    private $extractor;

    protected function setUp()
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $this->extractor = new SerializerExtractor($classMetadataFactory);
    }

    public function testGetProperties()
    {
        $this->assertEquals(
            array('collection'),
            $this->extractor->getProperties('Symphony\Component\PropertyInfo\Tests\Fixtures\Dummy', array('serializer_groups' => array('a')))
        );
    }
}
