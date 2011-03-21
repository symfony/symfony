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
 * Clear and Warmup the cache.
 *
 * @author Francis Besset <francis.besset@gmail.com>
 */
class CacheClearCommand extends CacheWarmupCommand
{
    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setName('cache:clear')
            ->setDefinition(array(
                new InputOption('warmup', '', InputOption::VALUE_NONE, 'Warms up the cache')
            ))
            ->setDescription('Clear the cache')
            ->setHelp(<<<EOF
The <info>cache:clear</info> command clear the cache.

<info>./app/console cache:clear --warmup</info>

Warmup option, warms up the cache.
EOF
            )
        ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);

        $this->cacheDir = $this->container->getParameter('kernel.environment').'_tmp';
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $realCacheDir = $this->container->getParameter('kernel.cache_dir');
        $oldCacheDir  = $realCacheDir.'_old';

        if (!is_writable($realCacheDir)) {
            throw new \RuntimeException(sprintf('Unable to write %s directory', $this->realCacheDir));
        }

        if (!$input->getOption('warmup')) {
            $output->writeln('Clear cache');

            rename($realCacheDir, $oldCacheDir);
        } else {
            parent::execute($input, $output);

            $output->writeln('Move cache directories');
            rename($realCacheDir, $oldCacheDir);
            rename($this->kernelTmp->getCacheDir(), $realCacheDir);

            $output->writeln('Clear the old cache');
        }

        $this->container->get('filesystem')->remove($oldCacheDir);
    }
}
