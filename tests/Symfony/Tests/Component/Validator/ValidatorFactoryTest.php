<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Validator;

use Symfony\Component\Validator\Validator;
use Symfony\Component\Validator\ValidatorContext;
use Symfony\Component\Validator\ValidatorFactory;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\Mapping\ClassMetadataFactory;
use Symfony\Component\Validator\Mapping\Loader\XmlFilesLoader;
use Symfony\Component\Validator\Mapping\Loader\YamlFilesLoader;
use Symfony\Component\Validator\Mapping\Loader\AnnotationLoader;
use Symfony\Component\Validator\Mapping\Loader\StaticMethodLoader;
use Symfony\Component\Validator\Mapping\Loader\LoaderChain;

class ValidatorFactoryTest extends \PHPUnit_Framework_TestCase
{
    protected $defaultContext;
    protected $factory;

    protected function setUp()
    {
        $this->defaultContext = new ValidatorContext();
        $this->factory = new ValidatorFactory($this->defaultContext);
    }

    public function testOverrideClassMetadataFactory()
    {
        $factory1 = $this->getMock('Symfony\Component\Validator\Mapping\ClassMetadataFactoryInterface');
        $factory2 = $this->getMock('Symfony\Component\Validator\Mapping\ClassMetadataFactoryInterface');

        $this->defaultContext->classMetadataFactory($factory1);

        $result = $this->factory->classMetadataFactory($factory2);

        $this->assertSame($factory1, $this->defaultContext->getClassMetadataFactory());
        $this->assertSame($factory2, $result->getClassMetadataFactory());
    }

    public function testOverrideConstraintValidatorFactory()
    {
        $factory1 = $this->getMock('Symfony\Component\Validator\ConstraintValidatorFactoryInterface');
        $factory2 = $this->getMock('Symfony\Component\Validator\ConstraintValidatorFactoryInterface');

        $this->defaultContext->constraintValidatorFactory($factory1);

        $result = $this->factory->constraintValidatorFactory($factory2);

        $this->assertSame($factory1, $this->defaultContext->getConstraintValidatorFactory());
        $this->assertSame($factory2, $result->getConstraintValidatorFactory());
    }

    public function testGetValidator()
    {
        $metadataFactory = $this->getMock('Symfony\Component\Validator\Mapping\ClassMetadataFactoryInterface');
        $validatorFactory = $this->getMock('Symfony\Component\Validator\ConstraintValidatorFactoryInterface');

        $this->defaultContext
            ->classMetadataFactory($metadataFactory)
            ->constraintValidatorFactory($validatorFactory);

        $validator = $this->factory->getValidator();

        $this->assertEquals(new Validator($metadataFactory, $validatorFactory), $validator);
    }

    public function testBuildDefaultFromAnnotations()
    {
        if (!class_exists('Doctrine\Common\Annotations\AnnotationReader')) {
            $this->markTestSkipped('Doctrine is required for this test');
        }
        $factory = ValidatorFactory::buildDefault();

        $context = new ValidatorContext();
        $context
            ->classMetadataFactory(new ClassMetadataFactory(new AnnotationLoader()))
            ->constraintValidatorFactory(new ConstraintValidatorFactory());

        $this->assertEquals(new ValidatorFactory($context), $factory);
    }

    public function testBuildDefaultFromAnnotationsWithCustomNamespaces()
    {
        if (!class_exists('Doctrine\Common\Annotations\AnnotationReader')) {
            $this->markTestSkipped('Doctrine is required for this test');
        }
        $factory = ValidatorFactory::buildDefault(array(), true, array(
            'myns' => 'My\\Namespace\\',
        ));

        $context = new ValidatorContext();
        $context
            ->classMetadataFactory(new ClassMetadataFactory(new AnnotationLoader(array(
                'myns' => 'My\\Namespace\\',
            ))))
            ->constraintValidatorFactory(new ConstraintValidatorFactory());

        $this->assertEquals(new ValidatorFactory($context), $factory);
    }

    public function testBuildDefaultFromXml()
    {
        $path = __DIR__.'/Mapping/Loader/constraint-mapping.xml';
        $factory = ValidatorFactory::buildDefault(array($path), false);

        $context = new ValidatorContext();
        $context
            ->classMetadataFactory(new ClassMetadataFactory(new XmlFilesLoader(array($path))))
            ->constraintValidatorFactory(new ConstraintValidatorFactory());

        $this->assertEquals(new ValidatorFactory($context), $factory);
    }

    public function testBuildDefaultFromYaml()
    {
        $path = __DIR__.'/Mapping/Loader/constraint-mapping.yml';
        $factory = ValidatorFactory::buildDefault(array($path), false);

        $context = new ValidatorContext();
        $context
            ->classMetadataFactory(new ClassMetadataFactory(new YamlFilesLoader(array($path))))
            ->constraintValidatorFactory(new ConstraintValidatorFactory());

        $this->assertEquals(new ValidatorFactory($context), $factory);
    }

    public function testBuildDefaultFromStaticMethod()
    {
        $path = __DIR__.'/Mapping/Loader/constraint-mapping.yml';
        $factory = ValidatorFactory::buildDefault(array(), false, null, 'loadMetadata');

        $context = new ValidatorContext();
        $context
            ->classMetadataFactory(new ClassMetadataFactory(new StaticMethodLoader('loadMetadata')))
            ->constraintValidatorFactory(new ConstraintValidatorFactory());

        $this->assertEquals(new ValidatorFactory($context), $factory);
    }

    public function testBuildDefaultFromMultipleLoaders()
    {
        if (!class_exists('Doctrine\Common\Annotations\AnnotationReader')) {
            $this->markTestSkipped('Doctrine is required for this test');
        }
        $xmlPath = __DIR__.'/Mapping/Loader/constraint-mapping.xml';
        $yamlPath = __DIR__.'/Mapping/Loader/constraint-mapping.yml';
        $factory = ValidatorFactory::buildDefault(array($xmlPath, $yamlPath), true, null, 'loadMetadata');

        $chain = new LoaderChain(array(
            new XmlFilesLoader(array($xmlPath)),
            new YamlFilesLoader(array($yamlPath)),
            new AnnotationLoader(),
            new StaticMethodLoader('loadMetadata'),
        ));

        $context = new ValidatorContext();
        $context
            ->classMetadataFactory(new ClassMetadataFactory($chain))
            ->constraintValidatorFactory(new ConstraintValidatorFactory());

        $this->assertEquals(new ValidatorFactory($context), $factory);
    }

    /**
     * @expectedException Symfony\Component\Validator\Exception\MappingException
     */
    public function testBuildDefaultThrowsExceptionIfNoLoaderIsFound()
    {
        ValidatorFactory::buildDefault(array(), false);
    }

    /**
     * @expectedException Symfony\Component\Validator\Exception\MappingException
     */
    public function testBuildDefaultThrowsExceptionIfUnknownExtension()
    {
        ValidatorFactory::buildDefault(array(
            __DIR__.'/Mapping/Loader/StaticMethodLoaderTest.php'
        ));
    }
}