<?php

namespace Symphony\Component\DependencyInjection\Tests;

use PHPUnit\Framework\TestCase;
use Symphony\Component\DependencyInjection\Container;
use Symphony\Component\DependencyInjection\ContainerBuilder;
use Symphony\Component\DependencyInjection\EnvVarProcessor;

class EnvVarProcessorTest extends TestCase
{
    const TEST_CONST = 'test';

    /**
     * @dataProvider validStrings
     */
    public function testGetEnvString($value, $processed)
    {
        $container = new ContainerBuilder();
        $container->setParameter('env(foo)', $value);
        $container->compile();

        $processor = new EnvVarProcessor($container);

        $result = $processor->getEnv('string', 'foo', function () {
            $this->fail('Should not be called');
        });

        $this->assertSame($processed, $result);
    }

    public function validStrings()
    {
        return array(
            array('hello', 'hello'),
            array('true', 'true'),
            array('false', 'false'),
            array('null', 'null'),
            array('1', '1'),
            array('0', '0'),
            array('1.1', '1.1'),
            array('1e1', '1e1'),
        );
    }

    /**
     * @dataProvider validBools
     */
    public function testGetEnvBool($value, $processed)
    {
        $processor = new EnvVarProcessor(new Container());

        $result = $processor->getEnv('bool', 'foo', function ($name) use ($value) {
            $this->assertSame('foo', $name);

            return $value;
        });

        $this->assertSame($processed, $result);
    }

    public function validBools()
    {
        return array(
            array('true', true),
            array('false', false),
            array('null', false),
            array('1', true),
            array('0', false),
            array('1.1', true),
            array('1e1', true),
        );
    }

    /**
     * @dataProvider validInts
     */
    public function testGetEnvInt($value, $processed)
    {
        $processor = new EnvVarProcessor(new Container());

        $result = $processor->getEnv('int', 'foo', function ($name) use ($value) {
            $this->assertSame('foo', $name);

            return $value;
        });

        $this->assertSame($processed, $result);
    }

    public function validInts()
    {
        return array(
            array('1', 1),
            array('1.1', 1),
            array('1e1', 10),
        );
    }

    /**
     * @expectedException \Symphony\Component\DependencyInjection\Exception\RuntimeException
     * @expectedExceptionMessage Non-numeric env var
     * @dataProvider invalidInts
     */
    public function testGetEnvIntInvalid($value)
    {
        $processor = new EnvVarProcessor(new Container());

        $processor->getEnv('int', 'foo', function ($name) use ($value) {
            $this->assertSame('foo', $name);

            return $value;
        });
    }

    public function invalidInts()
    {
        return array(
            array('foo'),
            array('true'),
            array('null'),
        );
    }

    /**
     * @dataProvider validFloats
     */
    public function testGetEnvFloat($value, $processed)
    {
        $processor = new EnvVarProcessor(new Container());

        $result = $processor->getEnv('float', 'foo', function ($name) use ($value) {
            $this->assertSame('foo', $name);

            return $value;
        });

        $this->assertSame($processed, $result);
    }

    public function validFloats()
    {
        return array(
            array('1', 1.0),
            array('1.1', 1.1),
            array('1e1', 10.0),
        );
    }

    /**
     * @expectedException \Symphony\Component\DependencyInjection\Exception\RuntimeException
     * @expectedExceptionMessage Non-numeric env var
     * @dataProvider invalidFloats
     */
    public function testGetEnvFloatInvalid($value)
    {
        $processor = new EnvVarProcessor(new Container());

        $processor->getEnv('float', 'foo', function ($name) use ($value) {
            $this->assertSame('foo', $name);

            return $value;
        });
    }

    public function invalidFloats()
    {
        return array(
            array('foo'),
            array('true'),
            array('null'),
        );
    }

    /**
     * @dataProvider validConsts
     */
    public function testGetEnvConst($value, $processed)
    {
        $processor = new EnvVarProcessor(new Container());

        $result = $processor->getEnv('const', 'foo', function ($name) use ($value) {
            $this->assertSame('foo', $name);

            return $value;
        });

        $this->assertSame($processed, $result);
    }

    public function validConsts()
    {
        return array(
            array('Symphony\Component\DependencyInjection\Tests\EnvVarProcessorTest::TEST_CONST', self::TEST_CONST),
            array('E_ERROR', E_ERROR),
        );
    }

    /**
     * @expectedException \Symphony\Component\DependencyInjection\Exception\RuntimeException
     * @expectedExceptionMessage undefined constant
     * @dataProvider invalidConsts
     */
    public function testGetEnvConstInvalid($value)
    {
        $processor = new EnvVarProcessor(new Container());

        $processor->getEnv('const', 'foo', function ($name) use ($value) {
            $this->assertSame('foo', $name);

            return $value;
        });
    }

    public function invalidConsts()
    {
        return array(
            array('Symphony\Component\DependencyInjection\Tests\EnvVarProcessorTest::UNDEFINED_CONST'),
            array('UNDEFINED_CONST'),
        );
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

    /**
     * @dataProvider validJson
     */
    public function testGetEnvJson($value, $processed)
    {
        $processor = new EnvVarProcessor(new Container());

        $result = $processor->getEnv('json', 'foo', function ($name) use ($value) {
            $this->assertSame('foo', $name);

            return $value;
        });

        $this->assertSame($processed, $result);
    }

    public function validJson()
    {
        return array(
            array('[1]', array(1)),
            array('{"key": "value"}', array('key' => 'value')),
            array(null, null),
        );
    }

    /**
     * @expectedException \Symphony\Component\DependencyInjection\Exception\RuntimeException
     * @expectedExceptionMessage Syntax error
     */
    public function testGetEnvInvalidJson()
    {
        $processor = new EnvVarProcessor(new Container());

        $processor->getEnv('json', 'foo', function ($name) {
            $this->assertSame('foo', $name);

            return 'invalid_json';
        });
    }

    /**
     * @expectedException \Symphony\Component\DependencyInjection\Exception\RuntimeException
     * @expectedExceptionMessage Invalid JSON env var
     * @dataProvider otherJsonValues
     */
    public function testGetEnvJsonOther($value)
    {
        $processor = new EnvVarProcessor(new Container());

        $processor->getEnv('json', 'foo', function ($name) use ($value) {
            $this->assertSame('foo', $name);

            return json_encode($value);
        });
    }

    public function otherJsonValues()
    {
        return array(
            array(1),
            array(1.1),
            array(true),
            array(false),
            array('foo'),
        );
    }

    /**
     * @expectedException \Symphony\Component\DependencyInjection\Exception\RuntimeException
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
