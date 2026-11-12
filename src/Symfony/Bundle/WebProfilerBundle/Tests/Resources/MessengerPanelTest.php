<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\WebProfilerBundle\Tests\Resources;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\RoutingExtension;
use Symfony\Bundle\WebProfilerBundle\Profiler\CodeExtension;
use Symfony\Bundle\WebProfilerBundle\Twig\WebProfilerExtension;
use Symfony\Component\ErrorHandler\ErrorRenderer\FileLinkFormatter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Profiler\Profile;
use Symfony\Component\Messenger\DataCollector\MessengerDataCollector;
use Symfony\Component\Messenger\DataCollector\WorkerMessageDataCollector;
use Symfony\Component\Messenger\Debug\WorkerMessageRequest;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ProfilerTokenStamp;
use Symfony\Component\Messenger\Stamp\SentStamp;
use Symfony\Component\Messenger\TraceableMessageBus;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class MessengerPanelTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(MessengerDataCollector::class)) {
            $this->markTestSkipped('The Messenger component is not installed.');
        }
    }

    public function testPanelRendersTheCallerExcerpt()
    {
        $bus = new TraceableMessageBus(new MessageBus());
        $bus->dispatch(new \stdClass());

        $panel = $this->renderPanel($bus);

        $this->assertStringContainsString('title="'.__FILE__.'">MessengerPanelTest.php</', $panel);
        $this->assertStringContainsString('<div class="trace"><ol start=', preg_replace('/>\s+</', '><', $panel));
    }

    public function testPanelRendersACallerWithoutFile()
    {
        $bus = new TraceableMessageBus(new MessageBus());

        // array_map() called by array_map() leaves no frame with a file, so the caller is unknown
        array_map('array_map', [$bus->dispatch(...)], [[new \stdClass()]]);

        $this->assertNull($bus->getDispatchedMessages()[0]['caller']['file']);

        $panel = $this->renderPanel($bus);

        $this->assertStringContainsString('<td class="message-bus-dispatch-caller">', $panel);
        $this->assertStringContainsString('<div class="trace"></div>', preg_replace('/>\s+</', '><', $panel));
    }

    public function testPanelLinksASentMessageToTheProfilesOfTheWorkersHandlingIt()
    {
        if (!class_exists(WorkerMessageDataCollector::class)) {
            $this->markTestSkipped('Profiling the messages consumed by workers requires the Messenger component 8.3 or higher.');
        }

        $bus = new TraceableMessageBus(new class implements MessageBusInterface {
            public function dispatch(object $message, array $stamps = []): Envelope
            {
                return Envelope::wrap($message, $stamps)->with(new ProfilerTokenStamp('abc123'), new SentStamp('sender', 'async'));
            }
        });
        $bus->dispatch(new \stdClass());

        $panel = $this->renderPanel($bus);

        $this->assertStringContainsString('Worker profiles', $panel);
        $this->assertStringContainsString('/_profiler_search_results?token=empty&amp;limit=10&amp;type=worker_message&amp;ip=abc123&amp;url=stdClass', $panel);
    }

    public function testPanelRendersTheMessageConsumedByAWorker()
    {
        if (!class_exists(WorkerMessageDataCollector::class)) {
            $this->markTestSkipped('Profiling the messages consumed by workers requires the Messenger component 8.3 or higher.');
        }

        $request = new WorkerMessageRequest(new Envelope(new \stdClass(), [new ProfilerTokenStamp('0r1g1n')]), 'async');
        $request->handled($request->envelope);

        $collector = new WorkerMessageDataCollector();
        $collector->collect($request, new Response());

        $panel = $this->render('worker_message', unserialize(serialize($collector)));

        $this->assertStringContainsString('Consumed Message', $panel);
        $this->assertStringContainsString('<td>async</td>', $panel);
        $this->assertStringContainsString('/_profiler?token=0r1g1n&amp;panel=messenger', $panel);
    }

    #[DataProvider('providePendingResults')]
    public function testSummaryTellsWhyAMessageIsPending(bool $hasErrors, string $explanation)
    {
        if (!class_exists(WorkerMessageDataCollector::class)) {
            $this->markTestSkipped('Profiling the messages consumed by workers requires the Messenger component 8.3 or higher.');
        }

        $profile = new Profile('w0rk3r');
        $profile->setMethod('async');
        $profile->setUrl(\stdClass::class);
        $profile->setIp('0r1g1n');
        $profile->setTime(time());
        $profile->setStatusCode(WorkerMessageRequest::PENDING);
        $profile->setHasErrors($hasErrors);

        $summary = $this->twig()->render('@WebProfiler/Profiler/_worker_message_summary.html.twig', [
            'profile' => $profile,
            'worker_message_collector' => false,
        ]);

        $this->assertStringContainsString('Pending', $summary);
        $this->assertStringContainsString($explanation, $summary);
        $this->assertStringContainsString('/_profiler?token=0r1g1n', $summary);
    }

    public static function providePendingResults(): iterable
    {
        yield 'deferred' => [false, 'acknowledged later, e.g. by a batch handler'];
        yield 'worker failed' => [true, 'the worker failed before the message was acknowledged'];
    }

    private function renderPanel(TraceableMessageBus $bus): string
    {
        $collector = new MessengerDataCollector();
        $collector->registerBus('messenger.bus.default', $bus);
        $collector->lateCollect();

        return $this->render('messenger', unserialize(serialize($collector)));
    }

    private function render(string $panel, object $collector): string
    {
        return $this->twig()
            ->load('@WebProfiler/Collector/'.$panel.'.html.twig')
            ->renderBlock('panel', ['collector' => $collector])
        ;
    }

    private function twig(): Environment
    {
        $loader = new FilesystemLoader();
        $loader->addPath(\dirname(__DIR__, 2).'/Resources/views', 'WebProfiler');

        $twig = new Environment($loader, ['strict_variables' => true]);
        $twig->addExtension(new WebProfilerExtension());
        $twig->addExtension(new CodeExtension(new FileLinkFormatter(), '', 'UTF-8'));
        $twig->addExtension(new RoutingExtension(new class implements UrlGeneratorInterface {
            public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
            {
                return '/'.$name.'?'.http_build_query($parameters);
            }

            public function setContext($context): void
            {
            }

            public function getContext(): RequestContext
            {
                return new RequestContext();
            }
        }));

        return $twig;
    }
}
