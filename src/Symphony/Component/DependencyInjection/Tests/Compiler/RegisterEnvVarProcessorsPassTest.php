<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\DependencyInjection\Tests\Compiler;

use PHPUnit\Framework\TestCase;
use Symphony\Component\DependencyInjection\Compiler\RegisterEnvVarProcessorsPass;
use Symphony\Component\DependencyInjection\ContainerBuilder;
use Symphony\Component\DependencyInjection\EnvVarProcessorInterface;

class RegisterEnvVarProcessorsPassTest extends TestCase
{
    public function testSimpleProcessor()
    {
        $container = new ContainerBuilder();
        $container->register('foo', SimpleProcessor::class)->addTag('container.env_var_processor');

        (new RegisterEnvVarProcessorsPass())->process($container);

        $this->assertTrue($container->has('container.env_var_processors_locator'));
        $this->assertInstanceOf(SimpleProcessor::class, $container->get('container.env_var_processors_locator')->get('foo'));

        $expected = array(
            'foo' => array('string'),
            'base64' => array('string'),
            'bool' => array('bool'),
            'const' => array('bool', 'int', 'float', 'string', 'array'),
            'csv' => array('array'),
            'file' => array('string'),
            'float' => array('float'),
            'int' => array('int'),
            'json' => array('array'),
            'resolve' => array('string'),
            'string' => array('string'),
        );

        $this->assertSame($expected, $container->getParameterBag()->getProvidedTypes());
    }

    public function testNoProcessor()
    {
        $container = new ContainerBuilder();

        (new RegisterEnvVarProcessorsPass())->process($container);

        $this->assertFalse($container->has('container.env_var_processors_locator'));
    }

    /**
     * @expectedException \Symphony\Component\DependencyInjection\Exception\InvalidArgumentException
     * @expectedExceptionMessage Invalid type "foo" returned by "Symphony\Component\DependencyInjection\Tests\Compiler\BadProcessor::getProvidedTypes()", expected one of "array", "bool", "float", "int", "string".
     */
    public function testBadProcessor()
    {
        $container = new ContainerBuilder();
        $container->register('foo', BadProcessor::class)->addTag('container.env_var_processor');

        (new RegisterEnvVarProcessorsPass())->process($container);
    }
}

class SimpleProcessor implements EnvVarProcessorInterface
{
    public function getEnv($prefix, $name, \Closure $getEnv)
    {
        return $getEnv($name);
    }

    public static function getProvidedTypes()
    {
        return array('foo' => 'string');
    }
}

class BadProcessor extends SimpleProcessor
{
    public static function getProvidedTypes()
    {
        return array('foo' => 'string|foo');
    }
}
