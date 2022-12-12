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

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

class FlexStyleMicroKernel extends Kernel
{
    use MicroKernelTrait {
        configureRoutes as traitConfigureRoutes;
    }

    private $cacheDir;

    public function halloweenAction(\stdClass $o)
    {
        return new Response($o->halloween);
    }

    #[Route('/easter')]
    public function easterAction()
    {
        return new Response('easter');
    }

    public function createHalloween(LoggerInterface $logger, string $halloween)
    {
        $o = new \stdClass();
        $o->logger = $logger;
        $o->halloween = $halloween;

        return $o;
    }

    public function getCacheDir(): string
    {
        return $this->cacheDir = sys_get_temp_dir().'/sf_flex_kernel';
    }

    public function getLogDir(): string
    {
        return $this->cacheDir;
    }

    public function getProjectDir(): string
    {
        return \dirname((new \ReflectionObject($this))->getFileName(), 2);
    }

    public function __sleep(): array
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

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $this->traitConfigureRoutes($routes);

        $routes->add('halloween', '/')->controller([$this, 'halloweenAction']);
        $routes->add('halloween2', '/h')->controller($this->halloweenAction(...));
    }

    protected function configureContainer(ContainerConfigurator $c): void
    {
        $c->parameters()
            ->set('halloween', 'Have a great day!');

        $c->services()
            ->set('logger', NullLogger::class)
            ->set('stdClass', 'stdClass')
                ->autowire()
                ->factory([$this, 'createHalloween'])
                ->arg('$halloween', '%halloween%');

        $c->extension('framework', [
            'http_method_override' => false,
            'router' => ['utf8' => true],
        ]);
    }
}
