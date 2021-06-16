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
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Compiler\CheckTypeDeclarationsPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\ParameterBag\EnvPlaceholderParameterBag;
use Symfony\Component\HttpKernel\Kernel;

final class ContainerLintCommand extends Command
{
    protected static $defaultName = 'lint:container';

    /**
     * @var ContainerBuilder
     */
    private $containerBuilder;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDescription('Ensure that arguments injected into services match type declarations')
            ->setHelp('This command parses service definitions and ensures that injected values match the type declarations of each services\' class.')
        ;
    }

    /**
     * {@inheritdoc}
     */
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

        $container->compile();

        return 0;
    }

    private function getContainerBuilder(): ContainerBuilder
    {
        if ($this->containerBuilder) {
            return $this->containerBuilder;
        }

        $kernel = $this->getApplication()->getKernel();
        $kernelContainer = $kernel->getContainer();

        if (!$kernel->isDebug() || !(new ConfigCache($kernelContainer->getParameter('debug.container.dump'), true))->isFresh()) {
            if (!$kernel instanceof Kernel) {
                throw new RuntimeException(sprintf('This command does not support the application kernel: "%s" does not extend "%s".', \get_class($kernel), Kernel::class));
            }

            $buildContainer = \Closure::bind(function (): ContainerBuilder {
                $this->initializeBundles();

                return $this->buildContainer();
            }, $kernel, \get_class($kernel));
            $container = $buildContainer();

            $skippedIds = [];
        } else {
            if (!$kernelContainer instanceof Container) {
                throw new RuntimeException(sprintf('This command does not support the application container: "%s" does not extend "%s".', \get_class($kernelContainer), Container::class));
            }

            (new XmlFileLoader($container = new ContainerBuilder($parameterBag = new EnvPlaceholderParameterBag()), new FileLocator()))->load($kernelContainer->getParameter('debug.container.dump'));

            $refl = new \ReflectionProperty($parameterBag, 'resolved');
            $refl->setAccessible(true);
            $refl->setValue($parameterBag, true);

            $skippedIds = [];
            foreach ($container->getServiceIds() as $serviceId) {
                if (str_starts_with($serviceId, '.errored.')) {
                    $skippedIds[$serviceId] = true;
                }
            }
        }

        $container->setParameter('container.build_hash', 'lint_container');
        $container->setParameter('container.build_id', 'lint_container');

        $container->addCompilerPass(new CheckTypeDeclarationsPass(true, $skippedIds), PassConfig::TYPE_AFTER_REMOVING, -100);

        return $this->containerBuilder = $container;
    }
}
