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

use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Translation\IdentityTranslator;
use Symfony\Component\Validator\ConstraintValidatorFactoryInterface;
use Symfony\Component\Validator\Exception\ValidatorException;
use Symfony\Component\Validator\Mapping\Factory\MetadataFactoryInterface;
use Symfony\Component\Validator\ObjectInitializerInterface;
use Symfony\Component\Validator\Validator\RecursiveValidator;
use Symfony\Component\Validator\ValidatorBuilder;

class ValidatorBuilderTest extends TestCase
{
    private ValidatorBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new ValidatorBuilder();
    }

    public function testAddObjectInitializer()
    {
        $this->assertSame($this->builder, $this->builder->addObjectInitializer(
            $this->createStub(ObjectInitializerInterface::class)
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

    public function testDisableAttributeMapping()
    {
        $this->assertSame($this->builder, $this->builder->disableAttributeMapping());
    }

    public function testSetMappingCache()
    {
        $this->assertSame($this->builder, $this->builder->setMappingCache($this->createStub(CacheItemPoolInterface::class)));
    }

    public function testSetSharedMetadataKey()
    {
        $this->assertSame($this->builder, $this->builder->setSharedMetadataKey('key'));
    }

    public function testValidatorsCreatedWithTheSameSharedMetadataKeyShareTheMetadataTheyLoad()
    {
        $metadata = $this->builder->setMappingCache(new ArrayAdapter())->setSharedMetadataKey(__METHOD__)->getValidator()->getMetadataFor(\stdClass::class);

        $this->assertSame($metadata, (new ValidatorBuilder())->setSharedMetadataKey(__METHOD__)->getValidator()->getMetadataFor(\stdClass::class));
        $this->assertNotSame($metadata, (new ValidatorBuilder())->setSharedMetadataKey(__METHOD__.'2')->getValidator()->getMetadataFor(\stdClass::class));
        $this->assertNotSame($metadata, (new ValidatorBuilder())->setMappingCache(new ArrayAdapter())->getValidator()->getMetadataFor(\stdClass::class));
    }

    public function testSharedMetadataKeyCannotBeSetAfterACustomMetadataFactory()
    {
        $this->builder->setMetadataFactory($this->createStub(MetadataFactoryInterface::class));

        $this->expectException(ValidatorException::class);

        $this->builder->setSharedMetadataKey('key');
    }

    public function testSetConstraintValidatorFactory()
    {
        $this->assertSame($this->builder, $this->builder->setConstraintValidatorFactory(
            $this->createStub(ConstraintValidatorFactoryInterface::class))
        );
    }

    public function testSetTranslator()
    {
        $this->assertSame($this->builder, $this->builder->setTranslator(new IdentityTranslator()));
    }

    public function testSetTranslationDomain()
    {
        $this->assertSame($this->builder, $this->builder->setTranslationDomain('TRANS_DOMAIN'));
    }

    public function testDisableTranslation()
    {
        $this->assertSame($this->builder, $this->builder->disableTranslation());
    }

    public function testEnablePropertyMetadataExistenceCheck()
    {
        $this->assertSame($this->builder, $this->builder->enablePropertyMetadataExistenceCheck());
    }

    public function testGetValidator()
    {
        $this->assertInstanceOf(RecursiveValidator::class, $this->builder->getValidator());
    }
}
