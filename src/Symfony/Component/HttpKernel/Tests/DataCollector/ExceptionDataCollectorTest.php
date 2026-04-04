<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\DataCollector;

use PHPUnit\Framework\TestCase;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\ExceptionDataCollector;

class ExceptionDataCollectorTest extends TestCase
{
    public function testCollect()
    {
        $e = new \Exception('foo', 500);
        $c = new ExceptionDataCollector();
        $flattened = FlattenException::createWithDataRepresentation($e);
        $trace = $flattened->getTrace();

        $this->assertFalse($c->hasException());

        $c->collect(new Request(), new Response(), $e);

        $this->assertTrue($c->hasException());
        $this->assertEquals($flattened, $c->getException());
        $this->assertSame('foo', $c->getMessage());
        $this->assertSame(500, $c->getCode());
        $this->assertSame('exception', $c->getName());
        $this->assertSame($trace, $c->getTrace());

        $c->collect(new Request(), new Response(), new class extends \Exception {
            protected $code = 'non-integer-code';
        });

        $this->assertSame('non-integer-code', $c->getCode());
    }

    public function testCollectWithoutException()
    {
        $c = new ExceptionDataCollector();
        $c->collect(new Request(), new Response());

        $this->assertFalse($c->hasException());
    }

    public function testReset()
    {
        $c = new ExceptionDataCollector();

        $c->collect(new Request(), new Response(), new \Exception());
        $c->reset();
        $c->collect(new Request(), new Response());

        $this->assertFalse($c->hasException());
    }

    public function testToJsonArrayWithoutException()
    {
        $c = new ExceptionDataCollector();
        $c->collect(new Request(), new Response());

        $json = $c->toJsonArray();

        $this->assertSame(['has_exception' => false], $json);
    }

    public function testToJsonArrayWithException()
    {
        $c = new ExceptionDataCollector();
        $c->collect(new Request(), new Response(), new \Exception('Test error', 42));

        $json = $c->toJsonArray();

        $this->assertTrue($json['has_exception']);
        $this->assertSame('Test error', $json['message']);
        $this->assertSame(42, $json['code']);
        $this->assertSame(500, $json['status_code']);
        $this->assertSame(\Exception::class, $json['class']);
        $this->assertSame(__FILE__, $json['file']);
        $this->assertIsInt($json['line']);
        $this->assertArrayNotHasKey('trace', $json);
    }

    public function testToJsonArrayVerboseIncludesTrace()
    {
        $c = new ExceptionDataCollector();
        $c->collect(new Request(), new Response(), new \Exception('Test error'));

        $json = $c->toJsonArray(verbose: true);

        $this->assertTrue($json['has_exception']);
        $this->assertArrayHasKey('trace', $json);
        $this->assertIsArray($json['trace']);
        $this->assertNotEmpty($json['trace']);
        // Verify args are stripped from trace frames
        foreach ($json['trace'] as $frame) {
            $this->assertArrayNotHasKey('args', $frame);
        }
    }

    public function testToJsonArrayVerboseTruncatesTrace()
    {
        $e = null;
        try {
            $this->causeDeepTrace(60);
        } catch (\Exception $caught) {
            $e = $caught;
        }

        $c = new ExceptionDataCollector();
        $c->collect(new Request(), new Response(), $e);

        $json = $c->toJsonArray(verbose: true);

        $this->assertLessThanOrEqual(50, \count($json['trace']));
    }

    private function causeDeepTrace(int $depth): void
    {
        if ($depth <= 0) {
            throw new \Exception('deep');
        }
        $this->causeDeepTrace($depth - 1);
    }
}
