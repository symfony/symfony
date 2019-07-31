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
use Symfony\Bridge\PhpUnit\ForwardCompatTestTrait;
use Symfony\Component\Validator\ValidatorBuilder;

class ValidatorBuilderTest extends TestCase
{
    use ForwardCompatTestTrait;

    /**
     * @var ValidatorBuilder
     */
    protected $builder;

    private function doSetUp()
    {
        $this->builder = new ValidatorBuilder();
    }

    private function doTearDown()
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

    public function testEnableAnnotationMapping()
    {
        $this->assertSame($this->builder, $this->builder->enableAnnotationMapping());
    }

    public function testDisableAnnotationMapping()
    {
        $this->assertSame($this->builder, $this->builder->disableAnnotationMapping());
    }

    public function testSetMetadataCache()
    {
        $this->assertSame($this->builder, $this->builder->setMetadataCache(
            $this->getMockBuilder('Symfony\Component\Validator\Mapping\Cache\CacheInterface')->getMock())
        );
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
