<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Kernel;

use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouteCollectionBuilder;

class ConcreteMicroKernel extends Kernel implements EventSubscriberInterface
{
    use MicroKernelTrait;

    private $cacheDir;

    public function onKernelException(GetResponseForExceptionEvent $event)
    {
        if ($event->getException() instanceof Danger) {
            $event->setResponse(Response::create('It\'s dangerous to go alone. Take this ⚔'));
        }
    }

    public function halloweenAction()
    {
        return new Response('halloween');
    }

    public function dangerousAction()
    {
        throw new Danger();
    }

    public function registerBundles()
    {
        return [
            new FrameworkBundle(),
        ];
    }

    public function getCacheDir()
    {
        return $this->cacheDir = sys_get_temp_dir().'/sf_micro_kernel';
    }

    public function getLogDir()
    {
        return $this->cacheDir;
    }

    public function __sleep()
    {
        throw new \BadMethodCallException('Cannot serialize '.__CLASS__);
    }

    public function __wakeup()
    {
        throw new \BadMethodCallException('Cannot unserialize '.__CLASS__);
    }

    public function __destruct()
    {
        $fs = new Filesystem();
        $fs->remove($this->cacheDir);
    }

    protected function configureRoutes(RouteCollectionBuilder $routes)
    {
        $routes->add('/', 'kernel::halloweenAction');
        $routes->add('/danger', 'kernel::dangerousAction');
    }

    protected function configureContainer(ContainerBuilder $c, LoaderInterface $loader)
    {
        $c->register('logger', NullLogger::class);
        $c->loadFromExtension('framework', [
            'secret' => '$ecret',
        ]);

        $c->setParameter('halloween', 'Have a great day!');
        $c->register('halloween', 'stdClass')->setPublic(true);
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }
}

class Danger extends \RuntimeException
{
}
