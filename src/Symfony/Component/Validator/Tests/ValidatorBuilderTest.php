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
use Symfony\Component\Validator\ValidatorBuilder;
use Symfony\Component\Validator\ValidatorBuilderInterface;

class ValidatorBuilderTest extends TestCase
{
    /**
     * @var ValidatorBuilderInterface
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

    public function testAddObjectInitializer(): void
    {
        $this->assertSame($this->builder, $this->builder->addObjectInitializer(
            $this->getMockBuilder('Symfony\Component\Validator\ObjectInitializerInterface')->getMock()
        ));
    }

    public function testAddObjectInitializers(): void
    {
        $this->assertSame($this->builder, $this->builder->addObjectInitializers(array()));
    }

    public function testAddXmlMapping(): void
    {
        $this->assertSame($this->builder, $this->builder->addXmlMapping('mapping'));
    }

    public function testAddXmlMappings(): void
    {
        $this->assertSame($this->builder, $this->builder->addXmlMappings(array()));
    }

    public function testAddYamlMapping(): void
    {
        $this->assertSame($this->builder, $this->builder->addYamlMapping('mapping'));
    }

    public function testAddYamlMappings(): void
    {
        $this->assertSame($this->builder, $this->builder->addYamlMappings(array()));
    }

    public function testAddMethodMapping(): void
    {
        $this->assertSame($this->builder, $this->builder->addMethodMapping('mapping'));
    }

    public function testAddMethodMappings(): void
    {
        $this->assertSame($this->builder, $this->builder->addMethodMappings(array()));
    }

    public function testEnableAnnotationMapping(): void
    {
        $this->assertSame($this->builder, $this->builder->enableAnnotationMapping());
    }

    public function testDisableAnnotationMapping(): void
    {
        $this->assertSame($this->builder, $this->builder->disableAnnotationMapping());
    }

    public function testSetMetadataCache(): void
    {
        $this->assertSame($this->builder, $this->builder->setMetadataCache(
            $this->getMockBuilder('Symfony\Component\Validator\Mapping\Cache\CacheInterface')->getMock())
        );
    }

    public function testSetConstraintValidatorFactory(): void
    {
        $this->assertSame($this->builder, $this->builder->setConstraintValidatorFactory(
            $this->getMockBuilder('Symfony\Component\Validator\ConstraintValidatorFactoryInterface')->getMock())
        );
    }

    public function testSetTranslator(): void
    {
        $this->assertSame($this->builder, $this->builder->setTranslator(
            $this->getMockBuilder('Symfony\Component\Translation\TranslatorInterface')->getMock())
        );
    }

    public function testSetTranslationDomain(): void
    {
        $this->assertSame($this->builder, $this->builder->setTranslationDomain('TRANS_DOMAIN'));
    }

    public function testGetValidator(): void
    {
        $this->assertInstanceOf('Symfony\Component\Validator\Validator\RecursiveValidator', $this->builder->getValidator());
    }
}
