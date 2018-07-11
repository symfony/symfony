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

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Config\ConfigCache;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CheckTypeHintsPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\Config\FileLocator;

class ContainerLintCommand extends Command
{
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
            ->setDescription('Lints container for services arguments type hints')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainerBuilder();

        $container->setParameter('container.build_id', 'lint_container');

        $container->addCompilerPass(new CheckTypeHintsPass(), PassConfig::TYPE_AFTER_REMOVING);

        $container->compile();
    }

    /**
     * Loads the ContainerBuilder from the cache.
     *
     * @return ContainerBuilder
     *
     * @throws \LogicException
     */
    protected function getContainerBuilder()
    {
        if ($this->containerBuilder) {
            return $this->containerBuilder;
        }

        $kernel = $this->getApplication()->getKernel();

        if (!$kernel->isDebug() || !(new ConfigCache($kernel->getContainer()->getParameter('debug.container.dump'), true))->isFresh()) {
            $buildContainer = \Closure::bind(function () { return $this->buildContainer(); }, $kernel, get_class($kernel));
            $container = $buildContainer();
            $container->getCompilerPassConfig()->setRemovingPasses(array());
            $container->compile();
        } else {
            (new XmlFileLoader($container = new ContainerBuilder(), new FileLocator()))->load($kernel->getContainer()->getParameter('debug.container.dump'));
        }

        return $this->containerBuilder = $container;
    }
}
