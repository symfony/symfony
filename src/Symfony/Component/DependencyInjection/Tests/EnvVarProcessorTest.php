<?php

namespace Symfony\Component\DependencyInjection\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\EnvVarProcessor;
use Symfony\Component\DependencyInjection\Exception\LogicException;

class EnvVarProcessorTest extends TestCase
{
    const TEST_CONST = 'test';

    public function testGetEnvString()
    {
        $container = new ContainerBuilder();
        $container->setParameter('env(foo)', 'hello');
        $container->compile();

        $processor = new EnvVarProcessor($container);

        $result = $processor->getEnv('string', 'foo', function () {
            throw new LogicException('Shouldnt be called');
        });

        $this->assertSame('hello', $result);
    }

    public function testGetEnvBool()
    {
        $processor = new EnvVarProcessor(new Container());

        $result = $processor->getEnv('bool', 'foo', function ($name) {
            $this->assertSame('foo', $name);

            return '1';
        });

        $this->assertTrue($result);
    }

    public function testGetEnvInt()
    {
        $processor = new EnvVarProcessor(new Container());

        $result = $processor->getEnv('int', 'foo', function ($name) {
            $this->assertSame('foo', $name);

            return '1';
        });

        $this->assertSame(1, $result);
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\RuntimeException
     * @expectedExceptionMessage Non-numeric env var
     */
    public function testGetEnvIntInvalid()
    {
        $processor = new EnvVarProcessor(new Container());

        $processor->getEnv('int', 'foo', function ($name) {
            $this->assertSame('foo', $name);

            return 'bar';
        });
    }

    public function testGetEnvFloat()
    {
        $processor = new EnvVarProcessor(new Container());

        $result = $processor->getEnv('float', 'foo', function ($name) {
            $this->assertSame('foo', $name);

            return '1.1';
        });

        $this->assertSame(1.1, $result);
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\RuntimeException
     * @expectedExceptionMessage Non-numeric env var
     */
    public function testGetEnvFloatInvalid()
    {
        $processor = new EnvVarProcessor(new Container());

        $processor->getEnv('float', 'foo', function ($name) {
            $this->assertSame('foo', $name);

            return 'bar';
        });
    }

    public function testGetEnvConst()
    {
        $processor = new EnvVarProcessor(new Container());

        $result = $processor->getEnv('const', 'foo', function ($name) {
            $this->assertSame('foo', $name);

            return 'Symfony\Component\DependencyInjection\Tests\EnvVarProcessorTest::TEST_CONST';
        });

        $this->assertSame(self::TEST_CONST, $result);
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\RuntimeException
     * @expectedExceptionMessage undefined constant
     */
    public function testGetEnvConstInvalid()
    {
        $processor = new EnvVarProcessor(new Container());

        $processor->getEnv('const', 'foo', function ($name) {
            $this->assertSame('foo', $name);

            return 'Symfony\Component\DependencyInjection\Tests\EnvVarProcessorTest::TEST_CONST_OTHER';
        });
    }

    public function testGetEnvBase64()
    {
        $processor = new EnvVarProcessor(new Container());

        $result = $processor->getEnv('base64', 'foo', function ($name) {
            $this->assertSame('foo', $name);

            return base64_encode('hello');
        });

        $this->assertSame('hello', $result);
    }

    public function testGetEnvJson()
    {
        $processor = new EnvVarProcessor(new Container());

        $result = $processor->getEnv('json', 'foo', function ($name) {
            $this->assertSame('foo', $name);

            return json_encode(array(1));
        });

        $this->assertSame(array(1), $result);
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\RuntimeException
     * @expectedExceptionMessage Invalid JSON env var
     */
    public function testGetEnvJsonOther()
    {
        $processor = new EnvVarProcessor(new Container());

        $processor->getEnv('json', 'foo', function ($name) {
            $this->assertSame('foo', $name);

            return json_encode(1);
        });
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\RuntimeException
     * @expectedExceptionMessage Unsupported env var prefix
     */
    public function testGetEnvUnknown()
    {
        $processor = new EnvVarProcessor(new Container());

        $processor->getEnv('unknown', 'foo', function ($name) {
            $this->assertSame('foo', $name);

            return 'foo';
        });
    }
}
