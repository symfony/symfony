<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\DependencyInjection\Reference;

class Issues49591Test extends TestCase
{
    public function testServices()
    {
        $containerBuilder = new ContainerBuilder();

        $containerBuilder->register('connection', Connection::class)
            ->addArgument(new ServiceLocatorArgument(new TaggedIteratorArgument('subscriber', needsIndexes: true)))
            ->setPublic(true);

        $containerBuilder->register('repository', Repository::class);

        $containerBuilder->register('session', Session::class)
            ->setFactory([new Reference('repository'), 'login'])
            ->addArgument(new Reference('connection'))
            ->setPublic(true);

        $containerBuilder->register('subscriber', Subscriber::class)
            ->addArgument(new Reference('session'))
            ->addTag('subscriber')
            ->setPublic(true);

        $containerBuilder->compile();

        $dumper = new PhpDumper($containerBuilder);
        $dump = $dumper->dump(['class' => 'Symfony_DI_PhpDumper_Issues49591']);

        eval('?>'.$dump);

        $container = new \Symfony_DI_PhpDumper_Issues49591();

        self::assertSame($container->get('session'), $container->get('subscriber')->session);
    }
}

class Connection
{
    public bool $connection = false;

    public function __construct(public ContainerInterface $container)
    {
    }

    public function connect()
    {
        if (!$this->connection) {
            $this->connection = true;
            $this->container->get('subscriber');
        }
    }
}

class Subscriber
{
    public function __construct(public Session $session)
    {
    }
}

class Repository
{
    public function login(Connection $connection)
    {
        $connection->connect();

        return new Session();
    }
}

class Session
{
}
