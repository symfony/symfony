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

use Symfony\Component\Cache\PruneableInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Cache pool pruner command.
 *
 * @author Rob Frawley 2nd <rmf@src.run>
 */
#[AsCommand(name: 'cache:pool:prune', description: 'Prune cache pools')]
final class CachePoolPruneCommand extends Command
{
    private iterable $pools;

    /**
     * @param iterable<mixed, PruneableInterface> $pools
     */
    public function __construct(iterable $pools)
    {
        parent::__construct();

        $this->pools = $pools;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command deletes all expired items from all pruneable pools.

    %command.full_name%
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

        foreach ($this->pools as $name => $pool) {
            $io->comment(sprintf('Pruning cache pool: <info>%s</info>', $name));
            $pool->prune();
        }

        $io->success('Successfully pruned cache pool(s).');

        return 0;
    }
}
