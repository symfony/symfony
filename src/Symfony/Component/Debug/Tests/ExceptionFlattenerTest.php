<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Debug\Tests;

use Symfony\Component\Debug\Exception\FlattenException;
use Symfony\Component\Debug\ExceptionFlattener;
use Symfony\Component\Debug\FlattenExceptionProcessorInterface;

class ExceptionFlattenerTest extends \PHPUnit_Framework_TestCase
{
    private $flattener;

    protected function setUp()
    {
        $this->flattener = new ExceptionFlattener();
    }

    public function testFlattenException()
    {
        $exception = new \RuntimeException('Runtime exception');
        $flattened = $this->flattener->flatten($exception);

        $this->assertEquals($exception->getMessage(), $flattened->getMessage());
        $this->assertEquals($exception->getCode(), $flattened->getCode());
        $this->assertEquals($exception->getFile(), $flattened->getFile());
        $this->assertEquals($exception->getLine(), $flattened->getLine());
        $this->assertInstanceOf($flattened->getClass(), $exception);
    }

    public function testFlattenPreviousException()
    {
        $exception1 = new \OutOfRangeException('Out of range exception');
        $exception2 = new \InvalidArgumentException('Invalid argument exception', null, $exception1);
        $exception3 = new \RuntimeException('Runtime exception', null, $exception2);

        $flattened = $this->flattener->flatten($exception3);
        $this->assertCount(2, $flattened->getAllPrevious());
        $this->assertInstanceOf('Symfony\Component\Debug\Exception\FlattenException', $flattened->getPrevious());
        $this->assertInstanceOf(
            'Symfony\Component\Debug\Exception\FlattenException',
            $flattened->getPrevious()->getPrevious()
        );
    }

    public function testFlattenWithProcessor()
    {
        $this->flattener->addProcessor(new TagTraceProcessor());

        $exception = new \RuntimeException('Runtime exception');
        $flattened = $this->flattener->flatten($exception);
        foreach ($flattened->getTrace() as $position => $entry) {
            if (-1 === $position) {
                $this->assertFalse(array_key_exists('tag', $entry));
            } else {
                $this->assertArrayHasKey('tag', $entry);
            }
        }
    }

    public function testProcessorReplaceException()
    {
        $this->flattener->addProcessor(new EmptyExceptionProcessor());

        $exception = new \RuntimeException('Runtime exception');
        $flattened = $this->flattener->flatten($exception);

        $this->assertNull($flattened->getMessage());
        $this->assertNull($flattened->getCode());
        $this->assertNull($flattened->getFile());
        $this->assertNull($flattened->getLine());
    }

    public function testProcessOnlyMaterException()
    {
        $exception1 = new \OutOfRangeException('Out of range exception');
        $exception2 = new \InvalidArgumentException('Invalid argument exception', null, $exception1);
        $exception3 = new \RuntimeException('Runtime exception', null, $exception2);

        $this->flattener->addProcessor(new MasterExtraProcessor());
        $flattened = $this->flattener->flatten($exception3);

        $this->assertEquals(array('tags' => array('master')), $flattened->getExtras());
        foreach ($flattened->getAllPrevious() as $exception) {
            $this->assertEquals(array(), $exception->getExtras());
        }
    }
}

class TagTraceProcessor implements FlattenExceptionProcessorInterface
{
    public function process(\Exception $exception, FlattenException $flattenException, $master)
    {
        $trace = $flattenException->getTrace();

        foreach ($exception->getTrace() as $key => $entry) {
            if (!isset($trace[$key])) {
                continue;
            }

            $trace[$key]['tag'] = 'value';
        }

        $flattenException->replaceTrace($trace);
    }
}

class EmptyExceptionProcessor implements FlattenExceptionProcessorInterface
{
    public function process(\Exception $exception, FlattenException $flattenException, $master)
    {
        return new FlattenException();
    }
}

class MasterExtraProcessor implements FlattenExceptionProcessorInterface
{
    public function process(\Exception $exception, FlattenException $flattenException, $master)
    {
        if (!$master) {
            return;
        }

        $flattenException->setExtra('tags', array('master'));
    }
}
