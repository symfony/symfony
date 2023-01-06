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
use Symfony\Component\Validator\ConstraintValidatorFactoryInterface;
use Symfony\Component\Validator\Mapping\Loader\AnnotationLoader;
use Symfony\Component\Validator\ObjectInitializerInterface;
use Symfony\Component\Validator\Validator\RecursiveValidator;
use Symfony\Component\Validator\ValidatorBuilder;
use Symfony\Contracts\Translation\TranslatorInterface;

class ValidatorBuilderTest extends TestCase
{
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
            $this->createMock(ObjectInitializerInterface::class)
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

    public function testEnableAnnotationMappingWithDefaultDoctrineAnnotationReader()
    {
        $this->assertSame($this->builder, $this->builder->enableAnnotationMapping());
        $this->assertSame($this->builder, $this->builder->addDefaultDoctrineAnnotationReader());

        $loaders = $this->builder->getLoaders();
        $this->assertCount(1, $loaders);
        $this->assertInstanceOf(AnnotationLoader::class, $loaders[0]);

        $r = new \ReflectionProperty(AnnotationLoader::class, 'reader');

        $this->assertInstanceOf(PsrCachedReader::class, $r->getValue($loaders[0]));
    }

    public function testEnableAnnotationMappingWithCustomDoctrineAnnotationReader()
    {
        $reader = $this->createMock(Reader::class);

        $this->assertSame($this->builder, $this->builder->enableAnnotationMapping());
        $this->assertSame($this->builder, $this->builder->setDoctrineAnnotationReader($reader));

        $loaders = $this->builder->getLoaders();
        $this->assertCount(1, $loaders);
        $this->assertInstanceOf(AnnotationLoader::class, $loaders[0]);

        $r = new \ReflectionProperty(AnnotationLoader::class, 'reader');

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
            $this->createMock(ConstraintValidatorFactoryInterface::class))
        );
    }

    public function testSetTranslator()
    {
        $this->assertSame($this->builder, $this->builder->setTranslator(
            $this->createMock(TranslatorInterface::class))
        );
    }

    public function testSetTranslationDomain()
    {
        $this->assertSame($this->builder, $this->builder->setTranslationDomain('TRANS_DOMAIN'));
    }

    public function testGetValidator()
    {
        $this->assertInstanceOf(RecursiveValidator::class, $this->builder->getValidator());
    }
}
