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
use Symfony\Component\Console\Helper\Dumper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Gets a cache pool key value.
 *
 * @author Antoine Makdessi <amakdessi@me.com>
 */
final class CachePoolGetCommand extends Command
{
    protected static $defaultName = 'cache:pool:get';

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDescription('Gets a cache pool key value.')
            ->addArgument('pool', InputArgument::REQUIRED, 'The cache pool from which to get a key')
            ->addArgument('keys', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'The cache key(s) to get a value on')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> gets a value from given cache pool key(s).

    %command.full_name% <pool> <keys>
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
        $dump = new Dumper($output);

        $keys = $input->getArgument('keys');
        foreach ($keys as $key) {
            $io->section(sprintf('"%s" "%s"', $pool, $key));

            if ($cache->hasItem($key)) {
                $io->writeln($dump($cache->getItem($key)->get()));
            } else {
                $io->note(sprintf('Cache item key "%s" does not exist in cache pool "%s".', $key, $pool));
            }
        }

        return 0;
    }
}
