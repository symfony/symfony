<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Scheduler\SchedulerRegistryInterface;
use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Scheduler\Task\TaskList;
use Symfony\Component\Scheduler\Worker\WorkerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskSubscriber implements EventSubscriberInterface
{
    private $schedulerRegistry;
    private $tasksPath;
    private $worker;

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => [['onKernelRequest', 50]],
        ];
    }

    /**
     * @param string $tasksPath The path that trigger this listener
     */
    public function __construct(SchedulerRegistryInterface $schedulerRegistry, WorkerInterface $worker, string $tasksPath = '/_tasks')
    {
        $this->schedulerRegistry = $schedulerRegistry;
        $this->worker = $worker;
        $this->tasksPath = $tasksPath;
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if ($this->tasksPath !== rawurldecode($request->getPathInfo())) {
            return;
        }

        $query = $request->query->all();

        if (Request::METHOD_GET === $request->getMethod() && (!\array_key_exists('name', $query) && !\array_key_exists('expression', $query))) {
            throw new \InvalidArgumentException('A GET request should at least contains a task name or its expression!');
        }

        $tasks = new TaskList();
        $schedulers = $this->schedulerRegistry->toArray();

        if (\array_key_exists('name', $query) && $name = $query['name']) {
            $request->attributes->set('task_filter', $name);

            foreach ($schedulers as $scheduler) {
                $filteredTasks = $scheduler->getTasks()->filter(function (TaskInterface $task) use ($name): bool {
                    return $name === $task->getName();
                });
                $tasks->addMultiples($filteredTasks->toArray());
            }
        }

        if (\array_key_exists('expression', $query) && $expression = $query['expression']) {
            $request->attributes->set('task_filter', $expression);

            foreach ($schedulers as $scheduler) {
                $filteredTasks = $scheduler->getTasks()->filter(function (TaskInterface $task) use ($expression): bool {
                    return $expression === $task->get('expression');
                });
                $tasks->addMultiples($filteredTasks->toArray());
            }
        }

        foreach ($tasks as $task) {
            $this->worker->execute($task);
        }
    }
}
