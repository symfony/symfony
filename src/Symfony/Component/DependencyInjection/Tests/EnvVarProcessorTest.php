<?php

namespace Symfony\Component\DependencyInjection\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\EnvVarProcessor;

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
        return [
            ['hello', 'hello'],
            ['true', 'true'],
            ['false', 'false'],
            ['null', 'null'],
            ['1', '1'],
            ['0', '0'],
            ['1.1', '1.1'],
            ['1e1', '1e1'],
        ];
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
        return [
            ['true', true],
            ['false', false],
            ['null', false],
            ['1', true],
            ['0', false],
            ['1.1', true],
            ['1e1', true],
        ];
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
        return [
            ['1', 1],
            ['1.1', 1],
            ['1e1', 10],
        ];
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\RuntimeException
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
        return [
            ['foo'],
            ['true'],
            ['null'],
        ];
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
        return [
            ['1', 1.0],
            ['1.1', 1.1],
            ['1e1', 10.0],
        ];
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\RuntimeException
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
        return [
            ['foo'],
            ['true'],
            ['null'],
        ];
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
        return [
            ['Symfony\Component\DependencyInjection\Tests\EnvVarProcessorTest::TEST_CONST', self::TEST_CONST],
            ['E_ERROR', E_ERROR],
        ];
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\RuntimeException
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
        return [
            ['Symfony\Component\DependencyInjection\Tests\EnvVarProcessorTest::UNDEFINED_CONST'],
            ['UNDEFINED_CONST'],
        ];
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

    public function testGetEnvTrim()
    {
        $processor = new EnvVarProcessor(new Container());

        $result = $processor->getEnv('trim', 'foo', function ($name) {
            $this->assertSame('foo', $name);

            return " hello\n";
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
        return [
            ['[1]', [1]],
            ['{"key": "value"}', ['key' => 'value']],
            [null, null],
        ];
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\RuntimeException
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
     * @expectedException \Symfony\Component\DependencyInjection\Exception\RuntimeException
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
        return [
            [1],
            [1.1],
            [true],
            [false],
            ['foo'],
        ];
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

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\RuntimeException
     * @expectedExceptionMessage Invalid env "key:foo": a key specifier should be provided.
     */
    public function testGetEnvKeyInvalidKey()
    {
        $processor = new EnvVarProcessor(new Container());

        $processor->getEnv('key', 'foo', function ($name) {
            $this->fail('Should not get here');
        });
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\RuntimeException
     * @expectedExceptionMessage Resolved value of "foo" did not result in an array value.
     * @dataProvider noArrayValues
     */
    public function testGetEnvKeyNoArrayResult($value)
    {
        $processor = new EnvVarProcessor(new Container());

        $processor->getEnv('key', 'index:foo', function ($name) use ($value) {
            $this->assertSame('foo', $name);

            return $value;
        });
    }

    public function noArrayValues()
    {
        return [
            [null],
            ['string'],
            [1],
            [true],
        ];
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\EnvNotFoundException
     * @expectedExceptionMessage Key "index" not found in
     * @dataProvider invalidArrayValues
     */
    public function testGetEnvKeyArrayKeyNotFound($value)
    {
        $processor = new EnvVarProcessor(new Container());

        $processor->getEnv('key', 'index:foo', function ($name) use ($value) {
            $this->assertSame('foo', $name);

            return $value;
        });
    }

    public function invalidArrayValues()
    {
        return [
            [[]],
            [['index2' => 'value']],
            [['index', 'index2']],
        ];
    }

    /**
     * @dataProvider arrayValues
     */
    public function testGetEnvKey($value)
    {
        $processor = new EnvVarProcessor(new Container());

        $this->assertSame($value['index'], $processor->getEnv('key', 'index:foo', function ($name) use ($value) {
            $this->assertSame('foo', $name);

            return $value;
        }));
    }

    public function arrayValues()
    {
        return [
            [['index' => 'password']],
            [['index' => 'true']],
            [['index' => false]],
            [['index' => '1']],
            [['index' => 1]],
            [['index' => '1.1']],
            [['index' => 1.1]],
            [['index' => []]],
            [['index' => ['val1', 'val2']]],
        ];
    }

    public function testGetEnvKeyChained()
    {
        $processor = new EnvVarProcessor(new Container());

        $this->assertSame('password', $processor->getEnv('key', 'index:file:foo', function ($name) {
            $this->assertSame('file:foo', $name);

            return [
                'index' => 'password',
            ];
        }));
    }

    /**
     * @dataProvider validNullables
     */
    public function testGetEnvNullable($value, $processed)
    {
        $processor = new EnvVarProcessor(new Container());
        $result = $processor->getEnv('default', ':foo', function ($name) use ($value) {
            $this->assertSame('foo', $name);

            return $value;
        });
        $this->assertSame($processed, $result);
    }

    public function validNullables()
    {
        return [
            ['hello', 'hello'],
            ['', null],
            ['null', 'null'],
            ['Null', 'Null'],
            ['NULL', 'NULL'],
         ];
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\EnvNotFoundException
     * @expectedExceptionMessage missing-file
     */
    public function testRequireMissingFile()
    {
        $processor = new EnvVarProcessor(new Container());

        $processor->getEnv('require', '/missing-file', function ($name) {
            return $name;
        });
    }

    public function testRequireFile()
    {
        $path = __DIR__.'/Fixtures/php/return_foo_string.php';

        $processor = new EnvVarProcessor(new Container());

        $result = $processor->getEnv('require', $path, function ($name) use ($path) {
            $this->assertSame($path, $name);

            return $path;
        });

        $this->assertEquals('foo', $result);
    }
}
