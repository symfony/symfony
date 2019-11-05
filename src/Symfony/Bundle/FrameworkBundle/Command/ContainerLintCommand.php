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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Compiler\CheckTypeDeclarationsPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

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
            ->setDescription('Ensures that arguments injected into services match type declarations')
            ->setHelp('This command parses service definitions and ensures that injected values match the type declarations of each services\' class.')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $container = $this->getContainerBuilder();

        $container->setParameter('container.build_hash', 'lint_container');
        $container->setParameter('container.build_time', time());
        $container->setParameter('container.build_id', 'lint_container');

        $container->addCompilerPass(new CheckTypeDeclarationsPass(true), PassConfig::TYPE_AFTER_REMOVING, -100);

        $container->compile();

        return 0;
    }

    private function getContainerBuilder(): ContainerBuilder
    {
        if ($this->containerBuilder) {
            return $this->containerBuilder;
        }

        $kernel = $this->getApplication()->getKernel();

        if (!$kernel->isDebug() || !(new ConfigCache($kernel->getContainer()->getParameter('debug.container.dump'), true))->isFresh()) {
            $buildContainer = \Closure::bind(function () { return $this->buildContainer(); }, $kernel, \get_class($kernel));
            $container = $buildContainer();
            $container->getCompilerPassConfig()->setRemovingPasses([]);
        } else {
            (new XmlFileLoader($container = new ContainerBuilder(), new FileLocator()))->load($kernel->getContainer()->getParameter('debug.container.dump'));
        }

        return $this->containerBuilder = $container;
    }
}
