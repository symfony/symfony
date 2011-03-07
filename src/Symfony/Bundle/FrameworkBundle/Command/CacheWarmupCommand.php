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
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Warmup the cache.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class CacheWarmupCommand extends Command
{
    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setName('cache:warmup')
            ->setDescription('Warms up an empty cache')
            ->setHelp(<<<EOF
The <info>cache:warmup</info> command warms up the cache.

Before running this command, the cache must be empty.
EOF
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Warming up the cache');

        $warmer = $this->container->get('cache_warmer');
        $warmer->enableOptionalWarmers();
        $warmer->warmUp($this->container->getParameter('kernel.cache_dir'));
    }
}
