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
 * @author Fabien Potencier <fabien@symfony.com>
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
                new InputOption('no-warmup', '', InputOption::VALUE_NONE, 'Do not warm up the cache')
            ))
            ->setDescription('Clear the cache')
            ->setHelp(<<<EOF
The <info>cache:clear</info> command clears the application cache for the current environment:

<info>./app/console cache:clear</info>
EOF
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $realCacheDir = $this->container->getParameter('kernel.cache_dir');
        $oldCacheDir  = $realCacheDir.'_old';

        if (!is_writable($realCacheDir)) {
            throw new \RuntimeException(sprintf('Unable to write in "%s" directory', $this->realCacheDir));
        }

        if ($input->getOption('no-warmup')) {
            rename($realCacheDir, $oldCacheDir);
        } else {
            $this->setWarmupDir($this->container->getParameter('kernel.environment').'_tmp');

            parent::execute($input, $output);

            rename($realCacheDir, $oldCacheDir);
            rename($this->kernelTmp->getCacheDir(), $realCacheDir);
        }

        $this->container->get('filesystem')->remove($oldCacheDir);
    }
}
