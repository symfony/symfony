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
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Compiler\CheckAliasValidityPass;
use Symfony\Component\DependencyInjection\Compiler\CheckTypeDeclarationsPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\Compiler\ResolveFactoryClassPass;
use Symfony\Component\DependencyInjection\Compiler\ResolveParameterPlaceHoldersPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\HttpKernel\Kernel;

#[AsCommand(name: 'lint:container', description: 'Ensure that arguments injected into services match type declarations')]
final class ContainerLintCommand extends Command
{
    private ContainerBuilder $container;

    protected function configure(): void
    {
        $this
            ->setHelp('This command parses service definitions and ensures that injected values match the type declarations of each services\' class.')
            ->addOption('resolve-env-vars', null, InputOption::VALUE_NONE, 'Resolve environment variables and fail if one is missing.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $errorIo = $io->getErrorStyle();

        $resolveEnvVars = $input->getOption('resolve-env-vars');

        try {
            $container = $this->getContainerBuilder($resolveEnvVars);
        } catch (RuntimeException $e) {
            $errorIo->error($e->getMessage());

            return 2;
        }

        $container->setParameter('container.build_time', time());

        try {
            $container->compile($resolveEnvVars);
        } catch (InvalidArgumentException $e) {
            $errorIo->error($e->getMessage());

            return 1;
        }

        $io->success('The container was linted successfully: all services are injected with values that are compatible with their type declarations.');

        return 0;
    }

    private function getContainerBuilder(bool $resolveEnvVars): ContainerBuilder
    {
        if (isset($this->container)) {
            return $this->container;
        }

        $kernel = $this->getApplication()->getKernel();
        $container = $kernel->getContainer();
        $file = $kernel->isDebug() ? $container->getParameter('debug.container.dump') : false;

        if (!$file || !(new ConfigCache($file, true))->isFresh()) {
            if (!$kernel instanceof Kernel) {
                throw new RuntimeException(\sprintf('This command does not support the application kernel: "%s" does not extend "%s".', get_debug_type($kernel), Kernel::class));
            }

            $buildContainer = \Closure::bind(function (): ContainerBuilder {
                $this->initializeBundles();

                return $this->buildContainer();
            }, $kernel, $kernel::class);
            $container = $buildContainer();
        } else {
            $container = unserialize(file_get_contents(substr_replace($file, '.ser', -4)));

            if (!$container instanceof ContainerBuilder) {
                throw new RuntimeException(\sprintf('This command does not support the application container: "%s" is not a "%s".', get_debug_type($container), ContainerBuilder::class));
            }

            if ($resolveEnvVars) {
                $container->getCompilerPassConfig()->setOptimizationPasses([new ResolveParameterPlaceHoldersPass(), new ResolveFactoryClassPass()]);
            } else {
                $parameterBag = $container->getParameterBag();
                $refl = new \ReflectionProperty($parameterBag, 'resolved');
                $refl->setValue($parameterBag, true);

                $container->getCompilerPassConfig()->setOptimizationPasses([new ResolveFactoryClassPass()]);
            }

            $container->getCompilerPassConfig()->setBeforeOptimizationPasses([]);
            $container->getCompilerPassConfig()->setBeforeRemovingPasses([]);
        }

        $container->setParameter('container.build_hash', 'lint_container');
        $container->setParameter('container.build_id', 'lint_container');
        $container->setParameter('container.runtime_mode', 'web=0');

        $container->addCompilerPass(new CheckAliasValidityPass(), PassConfig::TYPE_BEFORE_REMOVING, -100);
        $container->addCompilerPass(new CheckTypeDeclarationsPass(true), PassConfig::TYPE_AFTER_REMOVING, -100);

        return $this->container = $container;
    }
}
