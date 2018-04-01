<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\FrameworkBundle\Tests\Kernel;

use Psr\Log\NullLogger;
use Symphony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symphony\Bundle\FrameworkBundle\FrameworkBundle;
use Symphony\Component\Config\Loader\LoaderInterface;
use Symphony\Component\DependencyInjection\ContainerBuilder;
use Symphony\Component\EventDispatcher\EventSubscriberInterface;
use Symphony\Component\Filesystem\Filesystem;
use Symphony\Component\HttpFoundation\Response;
use Symphony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symphony\Component\HttpKernel\Kernel;
use Symphony\Component\HttpKernel\KernelEvents;
use Symphony\Component\Routing\RouteCollectionBuilder;

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
        return array(
            new FrameworkBundle(),
        );
    }

    public function getCacheDir()
    {
        return $this->cacheDir = sys_get_temp_dir().'/sf_micro_kernel';
    }

    public function getLogDir()
    {
        return $this->cacheDir;
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
        $c->loadFromExtension('framework', array(
            'secret' => '$ecret',
        ));

        $c->setParameter('halloween', 'Have a great day!');
        $c->register('halloween', 'stdClass')->setPublic(true);
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::EXCEPTION => 'onKernelException',
        );
    }
}

class Danger extends \RuntimeException
{
}
