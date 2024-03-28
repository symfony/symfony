<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger;

use Amp\Cache\LocalCache;
use Amp\Cancellation;
use Amp\Parallel\Worker\Task;
use Amp\Sync\Channel;
use App\Kernel;
use Psr\Container\ContainerInterface;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Messenger\Stamp\AckStamp;

class DispatchTask implements Task
{
    private static ?LocalCache $cache = null;

    public function __construct(private Envelope $envelope, private array $stamps, private readonly string $env, private readonly bool $isDebug, private readonly string $projectDir)
    {
        if (!class_exists(LocalCache::class)) {
            throw new \LogicException(sprintf('Package "amp/cache" is required to use the "%s". Try running "composer require amphp/cache".', LocalCache::class));
        }
    }

    public function run(Channel $channel, Cancellation $cancellation): mixed
    {
        $container = $this->getContainer();
        $envelope = $this->dispatch($container, $channel);

        return $envelope->withoutStampsOfType(AckStamp::class);
    }

    private function dispatch(ContainerInterface $container, $channel)
    {
        $messageBus = $container->get(MessageBusInterface::class);

        return $messageBus->dispatch($this->envelope, $this->stamps);
    }

    private function getContainer()
    {
        $cache = self::$cache ??= new LocalCache();
        $container = $cache->get('cache-container');

        // if not in cache, create container
        if (!$container) {
            if (!method_exists(Dotenv::class, 'bootEnv')) {
                throw new \LogicException(sprintf("Method bootEnv de \"%s\" doesn't exist.", Dotenv::class));
            }

            (new Dotenv())->bootEnv($this->projectDir.'/.env');

            if (!class_exists(Kernel::class) && !isset($_ENV['KERNEL_CLASS'])) {
                throw new \LogicException('You must set the KERNEL_CLASS environment variable to the fully-qualified class name of your Kernel in .env or have "%s" class.', Kernel::class);
            } elseif (isset($_ENV['KERNEL_CLASS'])) {
                $kernel = new $_ENV['KERNEL_CLASS']($this->env, $this->isDebug);
            } else {
                $kernel = new Kernel($this->env, $this->isDebug);
            }

            $kernel->boot();

            $container = $kernel->getContainer();
            $cache->set('cache-container', $container);
        }

        return $container;
    }

    public function getEnvelope(): Envelope
    {
        return $this->envelope;
    }
}
