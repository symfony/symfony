<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\Tests\Debug;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Workflow\Debug\TraceableWorkflow;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\TransitionBlockerList;
use Symfony\Component\Workflow\Workflow;

class TraceableWorkflowTest extends TestCase
{
    private MockObject|Workflow $innerWorkflow;

    private Stopwatch $stopwatch;

    private TraceableWorkflow $traceableWorkflow;

    protected function setUp(): void
    {
        $this->innerWorkflow = $this->createMock(Workflow::class);
        $this->stopwatch = new Stopwatch();

        $this->traceableWorkflow = new TraceableWorkflow(
            $this->innerWorkflow,
            $this->stopwatch
        );
    }

    /**
     * @dataProvider provideFunctionNames
     */
    public function testCallsInner(string $function, array $args, mixed $returnValue)
    {
        $this->innerWorkflow->expects($this->once())
            ->method($function)
            ->willReturn($returnValue);

        $this->assertSame($returnValue, $this->traceableWorkflow->{$function}(...$args));

        $calls = $this->traceableWorkflow->getCalls();

        $this->assertCount(1, $calls);
        $this->assertSame($function, $calls[0]['method']);
        $this->assertArrayHasKey('duration', $calls[0]);
        $this->assertSame($returnValue, $calls[0]['return']);
    }

    public function testCallsInnerCatchesException()
    {
        $exception = new \Exception('foo');
        $this->innerWorkflow->expects($this->once())
            ->method('can')
            ->willThrowException($exception);

        try {
            $this->traceableWorkflow->can(new \stdClass(), 'foo');

            $this->fail('An exception should have been thrown.');
        } catch (\Exception $e) {
            $this->assertSame($exception, $e);

            $calls = $this->traceableWorkflow->getCalls();

            $this->assertCount(1, $calls);
            $this->assertSame('can', $calls[0]['method']);
            $this->assertArrayHasKey('duration', $calls[0]);
            $this->assertArrayHasKey('exception', $calls[0]);
            $this->assertSame($exception, $calls[0]['exception']);
        }
    }

    public static function provideFunctionNames(): \Generator
    {
        $subject = new \stdClass();

        yield ['getMarking', [$subject], new Marking(['place' => 1])];

        yield ['can', [$subject, 'foo'], true];

        yield ['buildTransitionBlockerList', [$subject, 'foo'], new TransitionBlockerList()];

        yield ['apply', [$subject, 'foo'], new Marking(['place' => 1])];

        yield ['getEnabledTransitions', [$subject], []];

        yield ['getEnabledTransition', [$subject, 'foo'], null];
    }
}
