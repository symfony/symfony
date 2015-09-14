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

use Symfony\Component\Debug\ArgumentsFlattenExceptionProcessor;
use Symfony\Component\Debug\ExceptionFlattener;
use Symfony\Component\VarDumper\Cloner\VarCloner;

class ArgumentsFlattenExceptionProcessorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ExceptionFlattener
     */
    private $flattener;

    protected function setUp()
    {
        $this->flattener = new ExceptionFlattener();
    }

    public function testProcessOnlyMasterException()
    {
        $exception = new \Exception('Previous exception');
        $flattenedException = $this->flattener->flatten($exception);

        $processor = new ArgumentsFlattenExceptionProcessor();
        $processor->process($exception, $flattenedException, false);

        $this->assertEmpty($flattenedException->getExtras());
        foreach ($flattenedException->getTrace() as $trace) {
            $this->assertEmpty($trace['args']);
        }
    }

    public function testMasterException()
    {
        $exception = new \Exception('Master exception');
        $flattenedException = $this->flattener->flatten($exception);

        $processor = new ArgumentsFlattenExceptionProcessor();
        $processor->process($exception, $flattenedException, true);

        $extras = $flattenedException->getExtras();
        $this->assertArrayHasKey('trace_arguments', $extras);

        foreach ($flattenedException->getTrace() as $trace) {
            foreach ($trace['args'] as $arg) {
                $this->assertEquals('link', $arg[0]);
                $this->assertArrayHasKey($arg[1], $extras['trace_arguments'][1]);
            }
        }
    }

    public function testMasterExceptionWithoutSharedVariables()
    {
        $exception = new \Exception('Master exception');
        $flattenedException = $this->flattener->flatten($exception);

        $processor = new ArgumentsFlattenExceptionProcessor(null, false);
        $processor->process($exception, $flattenedException, true);

        $this->assertArrayNotHasKey('trace_arguments', $flattenedException->getExtras());

        foreach ($flattenedException->getTrace() as $trace) {
            foreach ($trace['args'] as $arg) {
                $this->assertNotEquals('link', $arg[0]);
            }
        }
    }

    public function testWithLinksToVariablesAndCloner()
    {
        $exception = new \Exception('Master exception');
        $flattenedException = $this->flattener->flatten($exception);

        $processor = new ArgumentsFlattenExceptionProcessor(new VarCloner());
        $processor->process($exception, $flattenedException, true);

        $extras = $flattenedException->getExtras();
        $this->assertArrayHasKey('trace_arguments', $extras);
        $this->assertInstanceOf('Symfony\Component\VarDumper\Cloner\Data', $extras['trace_arguments']);

        foreach ($flattenedException->getTrace() as $trace) {
            foreach ($trace['args'] as $arg) {
                $this->assertEquals('link', $arg[0]);
            }
        }
    }

    public function testIdenticalFlattenedArguments()
    {
        $previousException = new \Exception('Previous exception');
        $exception = new \Exception('Master exception', 0, $previousException);
        $flattenedException = $this->flattener->flatten($exception);

        $processor = new ArgumentsFlattenExceptionProcessor(new VarCloner());
        $processor->process($exception, $flattenedException, true);

        $this->assertSame($flattenedException->getExtra('trace_arguments'), $flattenedException->getPrevious()->getExtra('trace_arguments'));
    }
}
