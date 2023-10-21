<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Command;

use Symfony\Component\Config\ConfigCache;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Compiler\CheckTypeDeclarationsPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\Compiler\ResolveFactoryClassPass;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\ParameterBag\EnvPlaceholderParameterBag;
use Symfony\Component\HttpKernel\Kernel;

#[AsCommand(name: 'lint:container', description: 'Ensure that arguments injected into services match type declarations')]
final class ContainerLintCommand extends Command
{
    private ContainerBuilder $container;

    protected function configure(): void
    {
        $this
            ->setHelp('This command parses service definitions and ensures that injected values match the type declarations of each services\' class.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $errorIo = $io->getErrorStyle();

        try {
            $container = $this->getContainerBuilder();
        } catch (RuntimeException $e) {
            $errorIo->error($e->getMessage());

            return 2;
        }

        $container->setParameter('container.build_time', time());

        try {
            $container->compile();
        } catch (InvalidArgumentException $e) {
            $errorIo->error($e->getMessage());

            return 1;
        }

        $io->success('The container was linted successfully: all services are injected with values that are compatible with their type declarations.');

        return 0;
    }

    private function getContainerBuilder(): ContainerBuilder
    {
        if (isset($this->container)) {
            return $this->container;
        }

        $kernel = $this->getApplication()->getKernel();
        $kernelContainer = $kernel->getContainer();

        if (!$kernel->isDebug() || !$kernelContainer->getParameter('debug.container.dump') || !(new ConfigCache($kernelContainer->getParameter('debug.container.dump'), true))->isFresh()) {
            if (!$kernel instanceof Kernel) {
                throw new RuntimeException(sprintf('This command does not support the application kernel: "%s" does not extend "%s".', get_debug_type($kernel), Kernel::class));
            }

            $buildContainer = \Closure::bind(function (): ContainerBuilder {
                $this->initializeBundles();

                return $this->buildContainer();
            }, $kernel, $kernel::class);
            $container = $buildContainer();
        } else {
            if (!$kernelContainer instanceof Container) {
                throw new RuntimeException(sprintf('This command does not support the application container: "%s" does not extend "%s".', get_debug_type($kernelContainer), Container::class));
            }

            (new XmlFileLoader($container = new ContainerBuilder($parameterBag = new EnvPlaceholderParameterBag()), new FileLocator()))->load($kernelContainer->getParameter('debug.container.dump'));

            $refl = new \ReflectionProperty($parameterBag, 'resolved');
            $refl->setValue($parameterBag, true);

            $container->getCompilerPassConfig()->setBeforeOptimizationPasses([]);
            $container->getCompilerPassConfig()->setOptimizationPasses([new ResolveFactoryClassPass()]);
            $container->getCompilerPassConfig()->setBeforeRemovingPasses([]);
        }

        $container->setParameter('container.build_hash', 'lint_container');
        $container->setParameter('container.build_id', 'lint_container');

        $container->addCompilerPass(new CheckTypeDeclarationsPass(true), PassConfig::TYPE_AFTER_REMOVING, -100);

        return $this->container = $container;
    }
}
