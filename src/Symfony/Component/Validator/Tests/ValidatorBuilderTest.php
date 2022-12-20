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

use Doctrine\Common\Annotations\PsrCachedReader;
use Doctrine\Common\Annotations\Reader;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\Validator\ConstraintValidatorFactoryInterface;
use Symfony\Component\Validator\Mapping\Loader\AnnotationLoader;
use Symfony\Component\Validator\ObjectInitializerInterface;
use Symfony\Component\Validator\Validator\RecursiveValidator;
use Symfony\Component\Validator\ValidatorBuilder;
use Symfony\Contracts\Translation\TranslatorInterface;

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
        self::assertSame($this->builder, $this->builder->addObjectInitializer(
            self::createMock(ObjectInitializerInterface::class)
        ));
    }

    public function testAddObjectInitializers()
    {
        self::assertSame($this->builder, $this->builder->addObjectInitializers([]));
    }

    public function testAddXmlMapping()
    {
        self::assertSame($this->builder, $this->builder->addXmlMapping('mapping'));
    }

    public function testAddXmlMappings()
    {
        self::assertSame($this->builder, $this->builder->addXmlMappings([]));
    }

    public function testAddYamlMapping()
    {
        self::assertSame($this->builder, $this->builder->addYamlMapping('mapping'));
    }

    public function testAddYamlMappings()
    {
        self::assertSame($this->builder, $this->builder->addYamlMappings([]));
    }

    public function testAddMethodMapping()
    {
        self::assertSame($this->builder, $this->builder->addMethodMapping('mapping'));
    }

    public function testAddMethodMappings()
    {
        self::assertSame($this->builder, $this->builder->addMethodMappings([]));
    }

    /**
     * @group legacy
     */
    public function testEnableAnnotationMapping()
    {
        $this->expectDeprecation('Since symfony/validator 5.2: Not passing true as first argument to "Symfony\Component\Validator\ValidatorBuilder::enableAnnotationMapping" is deprecated. Pass true and call "addDefaultDoctrineAnnotationReader()" if you want to enable annotation mapping with Doctrine Annotations.');
        self::assertSame($this->builder, $this->builder->enableAnnotationMapping());

        $loaders = $this->builder->getLoaders();
        self::assertCount(1, $loaders);
        self::assertInstanceOf(AnnotationLoader::class, $loaders[0]);

        $r = new \ReflectionProperty(AnnotationLoader::class, 'reader');
        $r->setAccessible(true);

        self::assertInstanceOf(PsrCachedReader::class, $r->getValue($loaders[0]));
    }

    public function testEnableAnnotationMappingWithDefaultDoctrineAnnotationReader()
    {
        self::assertSame($this->builder, $this->builder->enableAnnotationMapping(true));
        self::assertSame($this->builder, $this->builder->addDefaultDoctrineAnnotationReader());

        $loaders = $this->builder->getLoaders();
        self::assertCount(1, $loaders);
        self::assertInstanceOf(AnnotationLoader::class, $loaders[0]);

        $r = new \ReflectionProperty(AnnotationLoader::class, 'reader');
        $r->setAccessible(true);

        self::assertInstanceOf(PsrCachedReader::class, $r->getValue($loaders[0]));
    }

    /**
     * @group legacy
     */
    public function testEnableAnnotationMappingWithCustomDoctrineAnnotationReaderLegacy()
    {
        $reader = self::createMock(Reader::class);

        $this->expectDeprecation('Since symfony/validator 5.2: Passing an instance of "'.\get_class($reader).'" as first argument to "Symfony\Component\Validator\ValidatorBuilder::enableAnnotationMapping" is deprecated. Pass true instead and call setDoctrineAnnotationReader() if you want to enable annotation mapping with Doctrine Annotations.');
        self::assertSame($this->builder, $this->builder->enableAnnotationMapping($reader));

        $loaders = $this->builder->getLoaders();
        self::assertCount(1, $loaders);
        self::assertInstanceOf(AnnotationLoader::class, $loaders[0]);

        $r = new \ReflectionProperty(AnnotationLoader::class, 'reader');
        $r->setAccessible(true);

        self::assertSame($reader, $r->getValue($loaders[0]));
    }

    public function testEnableAnnotationMappingWithCustomDoctrineAnnotationReader()
    {
        $reader = self::createMock(Reader::class);

        self::assertSame($this->builder, $this->builder->enableAnnotationMapping(true));
        self::assertSame($this->builder, $this->builder->setDoctrineAnnotationReader($reader));

        $loaders = $this->builder->getLoaders();
        self::assertCount(1, $loaders);
        self::assertInstanceOf(AnnotationLoader::class, $loaders[0]);

        $r = new \ReflectionProperty(AnnotationLoader::class, 'reader');
        $r->setAccessible(true);

        self::assertSame($reader, $r->getValue($loaders[0]));
    }

    public function testDisableAnnotationMapping()
    {
        self::assertSame($this->builder, $this->builder->disableAnnotationMapping());
    }

    public function testSetMappingCache()
    {
        self::assertSame($this->builder, $this->builder->setMappingCache(self::createMock(CacheItemPoolInterface::class)));
    }

    public function testSetConstraintValidatorFactory()
    {
        self::assertSame($this->builder, $this->builder->setConstraintValidatorFactory(
            self::createMock(ConstraintValidatorFactoryInterface::class)));
    }

    public function testSetTranslator()
    {
        self::assertSame($this->builder, $this->builder->setTranslator(
            self::createMock(TranslatorInterface::class)));
    }

    public function testSetTranslationDomain()
    {
        self::assertSame($this->builder, $this->builder->setTranslationDomain('TRANS_DOMAIN'));
    }

    public function testGetValidator()
    {
        self::assertInstanceOf(RecursiveValidator::class, $this->builder->getValidator());
    }
}
