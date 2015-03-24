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

use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\ValidatorBuilder;
use Symfony\Component\Validator\ValidatorBuilderInterface;

class ValidatorBuilderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ValidatorBuilderInterface
     */
    protected $builder;

    protected function setUp()
    {
        $this->builder = new ValidatorBuilder();
    }

    protected function tearDown()
    {
        $this->builder = null;
    }

    public function testAddObjectInitializer()
    {
        $this->assertSame($this->builder, $this->builder->addObjectInitializer(
            $this->getMock('Symfony\Component\Validator\ObjectInitializerInterface')
        ));
    }

    public function testAddObjectInitializers()
    {
        $this->assertSame($this->builder, $this->builder->addObjectInitializers(array()));
    }

    public function testAddXmlMapping()
    {
        $this->assertSame($this->builder, $this->builder->addXmlMapping('mapping'));
    }

    public function testAddXmlMappings()
    {
        $this->assertSame($this->builder, $this->builder->addXmlMappings(array()));
    }

    public function testAddYamlMapping()
    {
        $this->assertSame($this->builder, $this->builder->addYamlMapping('mapping'));
    }

    public function testAddYamlMappings()
    {
        $this->assertSame($this->builder, $this->builder->addYamlMappings(array()));
    }

    public function testAddMethodMapping()
    {
        $this->assertSame($this->builder, $this->builder->addMethodMapping('mapping'));
    }

    public function testAddMethodMappings()
    {
        $this->assertSame($this->builder, $this->builder->addMethodMappings(array()));
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
            $this->getMock('Symfony\Component\Validator\Mapping\Cache\CacheInterface'))
        );
    }

    public function testSetConstraintValidatorFactory()
    {
        $this->assertSame($this->builder, $this->builder->setConstraintValidatorFactory(
            $this->getMock('Symfony\Component\Validator\ConstraintValidatorFactoryInterface'))
        );
    }

    public function testSetTranslator()
    {
        $this->assertSame($this->builder, $this->builder->setTranslator(
            $this->getMock('Symfony\Component\Translation\TranslatorInterface'))
        );
    }

    public function testSetTranslationDomain()
    {
        $this->assertSame($this->builder, $this->builder->setTranslationDomain('TRANS_DOMAIN'));
    }

    /**
     * @group legacy
     */
    public function testLegacyDefaultApiVersion()
    {
        $this->iniSet('error_reporting', -1 & ~E_USER_DEPRECATED);

        // Legacy compatible implementation
        $this->assertInstanceOf('Symfony\Component\Validator\Validator\LegacyValidator', $this->builder->getValidator());
    }

    public function testSetApiVersion25()
    {
        $this->assertSame($this->builder, $this->builder->setApiVersion(Validation::API_VERSION_2_5));
        $this->assertInstanceOf('Symfony\Component\Validator\Validator\RecursiveValidator', $this->builder->getValidator());
    }

    /**
     * @group legacy
     */
    public function testLegacySetApiVersion24And25()
    {
        $this->iniSet('error_reporting', -1 & ~E_USER_DEPRECATED);

        $this->assertSame($this->builder, $this->builder->setApiVersion(Validation::API_VERSION_2_5_BC));
        $this->assertInstanceOf('Symfony\Component\Validator\Validator\LegacyValidator', $this->builder->getValidator());
    }
}
