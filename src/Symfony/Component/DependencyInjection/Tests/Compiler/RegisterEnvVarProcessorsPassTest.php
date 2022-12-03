<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Compiler\RegisterEnvVarProcessorsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\EnvVarProcessorInterface;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

class RegisterEnvVarProcessorsPassTest extends TestCase
{
    public function testSimpleProcessor()
    {
        $container = new ContainerBuilder();
        $container->register('foo', SimpleProcessor::class)->addTag('container.env_var_processor');

        (new RegisterEnvVarProcessorsPass())->process($container);

        $this->assertTrue($container->has('container.env_var_processors_locator'));
        $this->assertInstanceOf(SimpleProcessor::class, $container->get('container.env_var_processors_locator')->get('foo'));

        $expected = [
            'foo' => ['string'],
            'base64' => ['string'],
            'bool' => ['bool'],
            'not' => ['bool'],
            'const' => ['bool', 'int', 'float', 'string', 'array'],
            'csv' => ['array'],
            'file' => ['string'],
            'float' => ['float'],
            'int' => ['int'],
            'json' => ['array'],
            'key' => ['bool', 'int', 'float', 'string', 'array'],
            'url' => ['array'],
            'query_string' => ['array'],
            'resolve' => ['string'],
            'default' => ['bool', 'int', 'float', 'string', 'array'],
            'string' => ['string'],
            'trim' => ['string'],
            'require' => ['bool', 'int', 'float', 'string', 'array'],
            'enum' => [\BackedEnum::class],
            'shuffle' => ['array'],
        ];

        $this->assertSame($expected, $container->getParameterBag()->getProvidedTypes());
    }

    public function testNoProcessor()
    {
        $container = new ContainerBuilder();

        (new RegisterEnvVarProcessorsPass())->process($container);

        $this->assertFalse($container->has('container.env_var_processors_locator'));
    }

    public function testBadProcessor()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid type "foo" returned by "Symfony\Component\DependencyInjection\Tests\Compiler\BadProcessor::getProvidedTypes()", expected one of "array", "bool", "float", "int", "string", "BackedEnum".');
        $container = new ContainerBuilder();
        $container->register('foo', BadProcessor::class)->addTag('container.env_var_processor');

        (new RegisterEnvVarProcessorsPass())->process($container);
    }
}

class SimpleProcessor implements EnvVarProcessorInterface
{
    public function getEnv(string $prefix, string $name, \Closure $getEnv): mixed
    {
        return $getEnv($name);
    }

    public static function getProvidedTypes(): array
    {
        return ['foo' => 'string'];
    }
}

class BadProcessor extends SimpleProcessor
{
    public static function getProvidedTypes(): array
    {
        return ['foo' => 'string|foo'];
    }
}
