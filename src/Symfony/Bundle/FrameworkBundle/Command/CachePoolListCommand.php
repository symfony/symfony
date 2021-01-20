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
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * List available cache pools.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
final class CachePoolListCommand extends Command
{
    protected static $defaultName = 'cache:pool:list';
    protected static $defaultDescription = 'List available cache pools';

    private $poolNames;

    public function __construct(array $poolNames)
    {
        parent::__construct();

        $this->poolNames = $poolNames;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDescription(self::$defaultDescription)
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command lists all available cache pools.
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

        $io->table(['Pool name'], array_map(function ($pool) {
            return [$pool];
        }, $this->poolNames));

        return 0;
    }
}
