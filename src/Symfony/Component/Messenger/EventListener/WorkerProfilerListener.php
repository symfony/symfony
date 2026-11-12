<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\EventListener;

use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Debug\CliRequest;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Component\Messenger\Debug\WorkerMessageRequest;
use Symfony\Component\Messenger\Event\SendMessageToTransportsEvent;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\Event\WorkerStartedEvent;
use Symfony\Component\Messenger\Event\WorkerStoppedEvent;
use Symfony\Component\Messenger\Stamp\ProfilerTokenStamp;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * Collects one profile per message consumed by a worker run with the "--profile" option.
 *
 * It also stamps the messages sent to transports with the token of the profile being
 * collected, so that the profiles of the workers handling them can link back to it.
 *
 * @internal
 */
final class WorkerProfilerListener implements EventSubscriberInterface
{
    private ?WorkerMessageRequest $request = null;
    private ?WorkerMessageReceivedEvent $receivedEvent = null;
    private ?CliRequest $cliRequest = null;
    private bool $disabled = false;

    public function __construct(
        private readonly Profiler $profiler,
        private readonly RequestStack $requestStack,
        private readonly Stopwatch $stopwatch,
        private readonly ?UrlGeneratorInterface $urlGenerator = null,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SendMessageToTransportsEvent::class => ['stampProfilerToken', -1024],
            WorkerStartedEvent::class => 'checkExecutionStrategy',
            // after the listeners that can tell the worker to skip the message
            WorkerMessageReceivedEvent::class => ['startProfile', -2048],
            // after the retry listener decided whether the message will be retried
            WorkerMessageHandledEvent::class => ['onHandled', -2048],
            WorkerMessageFailedEvent::class => ['onFailed', -2048],
            // before the services, the profiler included, are reset
            WorkerRunningEvent::class => ['saveProfile', -512],
            WorkerStoppedEvent::class => ['saveProfile', -512],
            // when an exception escapes the worker, before the profile of the command is saved
            ConsoleEvents::ERROR => ['onConsoleError', -1024],
            ConsoleEvents::TERMINATE => ['onConsoleTerminate', -2048],
        ];
    }

    public function stampProfilerToken(SendMessageToTransportsEvent $event): void
    {
        if (!$this->profiler->isEnabled() || !$request = $this->requestStack->getCurrentRequest()) {
            return;
        }

        // sub-requests are profiled as part of their main request, if at all
        if (!$request->attributes->has('_virtual_type')) {
            $request = $this->requestStack->getMainRequest();
        }

        $event->setEnvelope($event->getEnvelope()->withoutAll(ProfilerTokenStamp::class)->with(new ProfilerTokenStamp($this->profiler->reserveToken($request))));
    }

    public function checkExecutionStrategy(WorkerStartedEvent $event): void
    {
        // the same listener can serve several workers, e.g. in functional tests
        $this->disabled = false;

        if (!$this->profiler->isEnabled() || !($cliRequest = $this->requestStack->getCurrentRequest()) instanceof CliRequest || !isset($cliRequest->command->input)) {
            return;
        }

        $input = $cliRequest->command->input;

        // concurrent messages are handled in child processes, out of reach of the profiler
        if (!$input->hasOption('concurrency') || 1 >= (int) $input->getOption('concurrency')) {
            return;
        }

        $this->disabled = true;

        $output = $cliRequest->command->output;
        if ($output instanceof ConsoleOutputInterface) {
            $output = $output->getErrorOutput();
        }
        $output->writeln('<comment>The messages are not profiled one by one when the "--concurrency" option is greater than 1.</comment>');
    }

    public function startProfile(WorkerMessageReceivedEvent $event): void
    {
        if ($this->disabled || !$event->shouldHandle() || $this->request || !$this->profiler->isEnabled()) {
            return;
        }

        // only the workers run with the "--profile" option collect profiles
        if (!($cliRequest = $this->requestStack->getCurrentRequest()) instanceof CliRequest) {
            return;
        }

        // drop what was collected since the previous message, e.g. when batch handlers were flushed
        $this->profiler->reset();

        $this->cliRequest = $cliRequest;
        $this->receivedEvent = $event;
        $this->request = new WorkerMessageRequest($event->getEnvelope(), $event->getReceiverName());
        $this->request->attributes->set('_stopwatch_token', bin2hex(random_bytes(3)));

        $this->requestStack->push($this->request);
        $this->stopwatch->openSection();
    }

    public function onHandled(WorkerMessageHandledEvent $event): void
    {
        if ($this->request?->envelope->getMessage() === $event->getEnvelope()->getMessage()) {
            $this->request->handled($event->getEnvelope());
        }
    }

    public function onFailed(WorkerMessageFailedEvent $event): void
    {
        if ($this->request?->envelope->getMessage() === $event->getEnvelope()->getMessage()) {
            $this->request->failed($event->getEnvelope(), $event->getThrowable(), $event->willRetry());
        }
    }

    public function onConsoleError(ConsoleErrorEvent $event): void
    {
        // commands can also run while handling a message, e.g. the ones of scheduled tasks
        if ($this->request && $event->getCommand() === $this->cliRequest?->command) {
            $this->request->error ??= $event->getError();
        }
    }

    public function onConsoleTerminate(ConsoleTerminateEvent $event): void
    {
        if ($event->getCommand() !== $this->cliRequest?->command) {
            return;
        }

        // the profile of the command keeps what was collected, to tell what made the worker fail
        $this->save(resetProfiler: false);
    }

    public function saveProfile(): void
    {
        $this->save(resetProfiler: true);
    }

    private function save(bool $resetProfiler): void
    {
        if (!$request = $this->request) {
            return;
        }

        $receivedEvent = $this->receivedEvent;
        $this->request = $this->receivedEvent = null;

        try {
            // the section must be closed before saving the profile to allow late collect
            $this->stopwatch->stopSection($request->attributes->get('_stopwatch_token'));
        } catch (\LogicException) {
            // noop
        }

        try {
            // a listener told the worker to skip the message after its profile was started
            if (!$receivedEvent->shouldHandle()) {
                return;
            }

            if ($profile = $this->profiler->collect($request, $request->getResponse(), $request->error)) {
                $this->profiler->saveProfile($profile);
                $this->notify($profile->getToken());
            }
        } finally {
            $this->requestStack->pop();

            if ($resetProfiler) {
                // the next message gets a fresh profile, even when the worker does not reset services,
                // so the profile of the command only holds what was collected after the last message
                $this->profiler->reset();
            }
        }
    }

    private function notify(string $token): void
    {
        $output = $this->cliRequest?->command->output ?? null;

        if (!$this->urlGenerator || !$output instanceof ConsoleOutputInterface || !$output->isVerbose()) {
            return;
        }

        $output->getErrorOutput()->writeln(\sprintf(
            'See profile <href=%s>%s</>',
            $this->urlGenerator->generate('_profiler', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL),
            $token
        ));
    }
}
