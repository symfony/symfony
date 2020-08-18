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
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Sets a cache pool key value.
 *
 * @author Antoine Makdessi <amakdessi@me.com>
 */
final class CachePoolSetCommand extends Command
{
    protected static $defaultName = 'cache:pool:set';

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDescription('Sets a cache pool key value.')
            ->addArgument('pool', InputArgument::REQUIRED, 'The cache pool to which to set a key')
            ->addArgument('key', InputArgument::REQUIRED, 'The cache key to set a value on')
            ->addArgument('value', InputArgument::REQUIRED, 'The value to set in the cache pool key')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> sets a value on a key on a given cache pool.

    %command.full_name% <pool> <key> <value>
EOF
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $container = $this->getApplication()->getKernel()->getContainer();

        $pool = $input->getArgument('pool');
        if (!$container->has($pool)) {
            $io->error('Error loading the cache pool.');

            return 1;
        }

        $cache = $container->get($pool);

        $key = $input->getArgument('key');
        $value = $input->getArgument('value');

        $cacheItem = $cache->getItem($key);
        $cacheItem->set($value);
        $saved = $cache->save($cacheItem);

        if (!$saved) {
            $io->error('Error saving the value.');

            return 1;
        }

        $io->success(sprintf('Cache item value was successfully saved in "%s" "%s".', $pool, $key));

        return 0;
    }
}
