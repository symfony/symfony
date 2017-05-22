<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Worker\Loop;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Worker\Exception\StopException;
use Symfony\Component\Worker\MessageCollection;
use Symfony\Component\Worker\Router\RouterInterface;

/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class Loop implements ConfigurableLoopInterface
{
    private $router;
    private $eventDispatcher;
    private $logger;
    private $name;
    private $options;

    private $stopped;
    private $startedAt;
    private $lastHealthCheck;

    public function __construct(RouterInterface $router, EventDispatcherInterface $eventDispatcher = null, LoggerInterface $logger = null, $name = 'unnamed', array $options = array())
    {
        if (!extension_loaded('pcntl')) {
            throw new \RuntimeException('The pcntl extension is mandatory.');
        }

        $this->router = $router;
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger;
        $this->name = $name;
        $this->options = array_replace(array(
            'loop_sleep' => 200000,
            'health_check_interval' => 10,
        ), $options);

        $this->stopped = false;
        $this->startedAt = time();
        // Fake the date to trigger right now a new health check.
        $this->lastHealthCheck = 0;
    }

    public function run()
    {
        if (null !== $this->logger) {
            $this->logger->notice('Worker {worker} started.', array(
                'worker' => $this->name,
            ));
        }

        $this->dispatch(LoopEvents::RUN);

        try {
            loop:

            pcntl_signal_dispatch();

            if ($this->healthCheck()) {
                $this->dispatch(LoopEvents::HEALTH_CHECK);
            }

            if ($this->stopped) {
                return;
            }

            $this->dispatch(LoopEvents::WAKE_UP);

            while (false !== $messageCollection = $this->router->fetchMessages()) {
                if (!$messageCollection instanceof MessageCollection) {
                    throw new \RuntimeException('This is not a MessageCollection instance.');
                }
                if (null !== $this->logger) {
                    $this->logger->notice('New message.');
                }

                $result = $this->router->consume($messageCollection);

                if (null !== $this->logger) {
                    if (false === $result) {
                        $this->logger->warning('Messages consumed with failure.');
                    } else {
                        $this->logger->info('Messages consumed successfully.');
                    }
                }

                pcntl_signal_dispatch();

                if ($this->healthCheck()) {
                    $this->dispatch(LoopEvents::HEALTH_CHECK);
                }

                if ($this->stopped) {
                    return;
                }
            }

            $this->dispatch(LoopEvents::SLEEP);

            usleep($this->options['loop_sleep']);

            goto loop;
        } catch (StopException $e) {
            $this->stop('Force shut down of the worker because a StopException has been thrown.', $e);

            return;
        } catch (\Exception $e) {
        } catch (\Throwable $e) {
        }

        // Not possible, but here just in case.
        if (!isset($e)) {
            return;
        }

        if (null !== $this->logger) {
            $this->logger->error('Worker {worker} has errored, shutting down. ({message})', array(
                'exception' => $e,
                'worker' => $this->name,
                'message' => $e->getMessage(),
            ));
        }

        throw $e;
    }

    public function stop($message = 'unknown reason.', \Exception $exception = null)
    {
        $this->dispatch(LoopEvents::STOP);

        if (null !== $this->logger) {
            $this->logger->notice('Worker {worker} stopped ({message}).', array(
                'exception' => $exception,
                'message' => $message,
                'worker' => $this->name,
            ));
        }

        $this->stopped = true;
    }

    /**
     * @return int
     */
    public function getStartedAt()
    {
        return $this->startedAt;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    private function dispatch($eventName)
    {
        if (null === $this->eventDispatcher) {
            return;
        }

        $event = new LoopEvent($this);

        $this->eventDispatcher->dispatch($eventName, $event);
    }

    private function healthCheck()
    {
        if (time() >= $this->lastHealthCheck + $this->options['health_check_interval']) {
            $this->lastHealthCheck = time();

            return true;
        }

        return false;
    }
}
