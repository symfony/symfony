<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests;

use Doctrine\Common\Annotations\CachedReader;
use Doctrine\Common\Annotations\Reader;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\Validator\Mapping\Loader\AnnotationLoader;
use Symfony\Component\Validator\ValidatorBuilder;

class ValidatorBuilderTest extends TestCase
{
    use ExpectDeprecationTrait;

    /**
     * @var ValidatorBuilder
     */
    protected $builder;

    protected function setUp(): void
    {
        $this->builder = new ValidatorBuilder();
    }

    protected function tearDown(): void
    {
        $this->builder = null;
    }

    public function testAddObjectInitializer()
    {
        $this->assertSame($this->builder, $this->builder->addObjectInitializer(
            $this->getMockBuilder('Symfony\Component\Validator\ObjectInitializerInterface')->getMock()
        ));
    }

    public function testAddObjectInitializers()
    {
        $this->assertSame($this->builder, $this->builder->addObjectInitializers([]));
    }

    public function testAddXmlMapping()
    {
        $this->assertSame($this->builder, $this->builder->addXmlMapping('mapping'));
    }

    public function testAddXmlMappings()
    {
        $this->assertSame($this->builder, $this->builder->addXmlMappings([]));
    }

    public function testAddYamlMapping()
    {
        $this->assertSame($this->builder, $this->builder->addYamlMapping('mapping'));
    }

    public function testAddYamlMappings()
    {
        $this->assertSame($this->builder, $this->builder->addYamlMappings([]));
    }

    public function testAddMethodMapping()
    {
        $this->assertSame($this->builder, $this->builder->addMethodMapping('mapping'));
    }

    public function testAddMethodMappings()
    {
        $this->assertSame($this->builder, $this->builder->addMethodMappings([]));
    }

    /**
     * @group legacy
     */
    public function testEnableAnnotationMapping()
    {
        $this->expectDeprecation('Since symfony/validator 5.2: Not passing true as first argument to "Symfony\Component\Validator\ValidatorBuilder::enableAnnotationMapping" is deprecated. Pass true and call "addDefaultDoctrineAnnotationReader()" if you want to enable annotation mapping with Doctrine Annotations.');
        $this->assertSame($this->builder, $this->builder->enableAnnotationMapping());

        $loaders = $this->builder->getLoaders();
        $this->assertCount(1, $loaders);
        $this->assertInstanceOf(AnnotationLoader::class, $loaders[0]);

        $r = new \ReflectionProperty(AnnotationLoader::class, 'reader');
        $r->setAccessible(true);

        $this->assertInstanceOf(CachedReader::class, $r->getValue($loaders[0]));
    }

    public function testEnableAnnotationMappingWithDefaultDoctrineAnnotationReader()
    {
        $this->assertSame($this->builder, $this->builder->enableAnnotationMapping(true));
        $this->assertSame($this->builder, $this->builder->addDefaultDoctrineAnnotationReader());

        $loaders = $this->builder->getLoaders();
        $this->assertCount(1, $loaders);
        $this->assertInstanceOf(AnnotationLoader::class, $loaders[0]);

        $r = new \ReflectionProperty(AnnotationLoader::class, 'reader');
        $r->setAccessible(true);

        $this->assertInstanceOf(CachedReader::class, $r->getValue($loaders[0]));
    }

    /**
     * @group legacy
     */
    public function testEnableAnnotationMappingWithCustomDoctrineAnnotationReaderLegacy()
    {
        $reader = $this->createMock(Reader::class);

        $this->expectDeprecation('Since symfony/validator 5.2: Passing an instance of "'.\get_class($reader).'" as first argument to "Symfony\Component\Validator\ValidatorBuilder::enableAnnotationMapping" is deprecated. Pass true instead and call setDoctrineAnnotationReader() if you want to enable annotation mapping with Doctrine Annotations.');
        $this->assertSame($this->builder, $this->builder->enableAnnotationMapping($reader));

        $loaders = $this->builder->getLoaders();
        $this->assertCount(1, $loaders);
        $this->assertInstanceOf(AnnotationLoader::class, $loaders[0]);

        $r = new \ReflectionProperty(AnnotationLoader::class, 'reader');
        $r->setAccessible(true);

        $this->assertSame($reader, $r->getValue($loaders[0]));
    }

    public function testEnableAnnotationMappingWithCustomDoctrineAnnotationReader()
    {
        $reader = $this->createMock(Reader::class);

        $this->assertSame($this->builder, $this->builder->enableAnnotationMapping(true));
        $this->assertSame($this->builder, $this->builder->setDoctrineAnnotationReader($reader));

        $loaders = $this->builder->getLoaders();
        $this->assertCount(1, $loaders);
        $this->assertInstanceOf(AnnotationLoader::class, $loaders[0]);

        $r = new \ReflectionProperty(AnnotationLoader::class, 'reader');
        $r->setAccessible(true);

        $this->assertSame($reader, $r->getValue($loaders[0]));
    }

    public function testDisableAnnotationMapping()
    {
        $this->assertSame($this->builder, $this->builder->disableAnnotationMapping());
    }

    public function testSetMappingCache()
    {
        $this->assertSame($this->builder, $this->builder->setMappingCache($this->createMock(CacheItemPoolInterface::class)));
    }

    public function testSetConstraintValidatorFactory()
    {
        $this->assertSame($this->builder, $this->builder->setConstraintValidatorFactory(
            $this->getMockBuilder('Symfony\Component\Validator\ConstraintValidatorFactoryInterface')->getMock())
        );
    }

    public function testSetTranslator()
    {
        $this->assertSame($this->builder, $this->builder->setTranslator(
            $this->getMockBuilder('Symfony\Contracts\Translation\TranslatorInterface')->getMock())
        );
    }

    public function testSetTranslationDomain()
    {
        $this->assertSame($this->builder, $this->builder->setTranslationDomain('TRANS_DOMAIN'));
    }

    public function testGetValidator()
    {
        $this->assertInstanceOf('Symfony\Component\Validator\Validator\RecursiveValidator', $this->builder->getValidator());
    }
}
