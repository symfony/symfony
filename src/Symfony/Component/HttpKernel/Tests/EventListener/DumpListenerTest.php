<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\EventListener;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\HttpKernel\EventListener\DumpListener;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\VarDumper\VarDumper;

/**
 * DumpListenerTest
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class DumpListenerTest extends \PHPUnit_Framework_TestCase
{
    public function testSubscribedEvents()
    {
        $this->assertSame(
            array(KernelEvents::REQUEST => array('configure', 1024)),
            DumpListener::getSubscribedEvents()
        );
    }

    public function testConfigure()
    {
        $prevDumper = $this->getDumpHandler();

        $container = new ContainerBuilder();
        $container->setDefinition('var_dumper.cloner', new Definition('Symfony\Component\HttpKernel\Tests\EventListener\MockCloner'));
        $container->setDefinition('mock_dumper', new Definition('Symfony\Component\HttpKernel\Tests\EventListener\MockDumper'));

        ob_start();
        $exception = null;
        $listener = new DumpListener($container, 'mock_dumper');

        try {
            $listener->configure();

            $lazyDumper = $this->getDumpHandler();
            VarDumper::dump('foo');

            $loadedDumper = $this->getDumpHandler();
            VarDumper::dump('bar');

            $this->assertSame('+foo-+bar-', ob_get_clean());

            $listenerReflector = new \ReflectionClass($listener);
            $lazyReflector = new \ReflectionFunction($lazyDumper);
            $loadedReflector = new \ReflectionFunction($loadedDumper);

            $this->assertSame($listenerReflector->getFilename(), $lazyReflector->getFilename());
            $this->assertSame($listenerReflector->getFilename(), $loadedReflector->getFilename());
            $this->assertGreaterThan($lazyReflector->getStartLine(), $loadedReflector->getStartLine());
        } catch (\Exception $exception) {
        }

        VarDumper::setHandler($prevDumper);

        if (null !== $exception) {
            throw $exception;
        }
    }

    private function getDumpHandler()
    {
        $prevDumper = VarDumper::setHandler('var_dump');
        VarDumper::setHandler($prevDumper );

        return $prevDumper;
    }
}

class MockCloner
{
    public function cloneVar($var)
    {
        return $var.'-';
    }
}

class MockDumper
{
    public function dump($var)
    {
        echo '+'.$var;
    }
}
