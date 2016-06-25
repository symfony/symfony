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

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Warmup the cache.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class CacheWarmupCommand extends ContainerAwareCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('cache:warmup')
            ->setDefinition(array(
                new InputOption('no-optional-warmers', '', InputOption::VALUE_NONE, 'Skip optional cache warmers (faster)'),
            ))
            ->setDescription('Warms up an empty cache')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command warms up the cache.

Before running this command, the cache must be empty.

This command does not generate the classes cache (as when executing this
command, too many classes that should be part of the cache are already loaded
in memory). Use <comment>curl</comment> or any other similar tool to warm up
the classes cache if you want.

EOF
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $kernel = $this->getContainer()->get('kernel');
        $output->writeln(sprintf('Warming up the cache for the <info>%s</info> environment with debug <info>%s</info>', $kernel->getEnvironment(), var_export($kernel->isDebug(), true)));

        $warmer = $this->getContainer()->get('cache_warmer');

        if (!$input->getOption('no-optional-warmers')) {
            $warmer->enableOptionalWarmers();
        }

        $warmer->warmUp($this->getContainer()->getParameter('kernel.cache_dir'));
    }
}
