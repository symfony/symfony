<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\EventListener;

use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Debug\CliRequest;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Profiler\Profile;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * @internal
 *
 * @author Jules Pietri <jules@heahprod.com>
 */
final class ConsoleProfilerListener implements EventSubscriberInterface
{
    private ?\Throwable $error = null;
    /** @var \SplObjectStorage<Request, Profile> */
    private \SplObjectStorage $profiles;
    /** @var \SplObjectStorage<Request, ?Request> */
    private \SplObjectStorage $parents;

    public function __construct(
        private readonly Profiler $profiler,
        private readonly RequestStack $requestStack,
        private readonly Stopwatch $stopwatch,
        private readonly bool $cliMode,
        private readonly ?UrlGeneratorInterface $urlGenerator = null,
    ) {
        $this->profiles = new \SplObjectStorage();
        $this->parents = new \SplObjectStorage();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleEvents::COMMAND => ['initialize', 4096],
            ConsoleEvents::ERROR => ['catch', -2048],
            ConsoleEvents::TERMINATE => ['profile', -4096],
        ];
    }

    public function initialize(ConsoleCommandEvent $event): void
    {
        if (!$this->cliMode) {
            return;
        }

        $input = $event->getInput();
        if (!$input->hasOption('profile') || !$input->getOption('profile')) {
            $this->profiler->disable();

            return;
        }

        $request = $this->requestStack->getCurrentRequest();

        if (!$request instanceof CliRequest || $request->command !== $event->getCommand()) {
            return;
        }

        $request->attributes->set('_stopwatch_token', substr(hash('sha256', uniqid(mt_rand(), true)), 0, 6));
        $this->stopwatch->openSection();
    }

    public function catch(ConsoleErrorEvent $event): void
    {
        if (!$this->cliMode) {
            return;
        }

        $this->error = $event->getError();
    }

    public function profile(ConsoleTerminateEvent $event): void
    {
        if (!$this->cliMode || !$this->profiler->isEnabled()) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();

        if (!$request instanceof CliRequest || $request->command !== $event->getCommand()) {
            return;
        }

        if (null !== $sectionId = $request->attributes->get('_stopwatch_token')) {
            // we must close the section before saving the profile to allow late collect
            try {
                $this->stopwatch->stopSection($sectionId);
            } catch (\LogicException) {
                // noop
            }
        }

        $request->command->exitCode = $event->getExitCode();
        $request->command->interruptedBySignal = $event->getInterruptingSignal();

        $profile = $this->profiler->collect($request, $request->getResponse(), $this->error);
        $this->error = null;
        $this->profiles[$request] = $profile;

        if ($this->parents[$request] = $this->requestStack->getParentRequest()) {
            // do not save on sub commands
            return;
        }

        // attach children to parents
        foreach ($this->profiles as $request) {
            if (null !== $parentRequest = $this->parents[$request]) {
                if (isset($this->profiles[$parentRequest])) {
                    $this->profiles[$parentRequest]->addChild($this->profiles[$request]);
                }
            }
        }

        $output = $event->getOutput();
        $output = $output instanceof ConsoleOutputInterface && $output->isVerbose() ? $output->getErrorOutput() : null;

        // save profiles
        foreach ($this->profiles as $r) {
            $p = $this->profiles[$r];
            $this->profiler->saveProfile($p);

            if ($this->urlGenerator && $output) {
                $token = $p->getToken();
                $output->writeln(sprintf(
                    'See profile <href=%s>%s</>',
                    $this->urlGenerator->generate('_profiler', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL),
                    $token
                ));
            }
        }

        $this->profiles = new \SplObjectStorage();
        $this->parents = new \SplObjectStorage();
    }
}
