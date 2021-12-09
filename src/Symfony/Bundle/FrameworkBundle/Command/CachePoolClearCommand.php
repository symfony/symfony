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

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\CacheClearer\Psr6CacheClearer;

/**
 * Clear cache pools.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
#[AsCommand(name: 'cache:pool:clear', description: 'Clear cache pools')]
final class CachePoolClearCommand extends Command
{
    private $poolClearer;
    private ?array $poolNames;

    /**
     * @param string[]|null $poolNames
     */
    public function __construct(Psr6CacheClearer $poolClearer, array $poolNames = null)
    {
        parent::__construct();

        $this->poolClearer = $poolClearer;
        $this->poolNames = $poolNames;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDefinition([
                new InputArgument('pools', InputArgument::IS_ARRAY | InputArgument::REQUIRED, 'A list of cache pools or cache pool clearers'),
            ])
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command clears the given cache pools or cache pool clearers.

    %command.full_name% <cache pool or clearer 1> [...<cache pool or clearer N>]
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
        $kernel = $this->getApplication()->getKernel();
        $pools = [];
        $clearers = [];

        foreach ($input->getArgument('pools') as $id) {
            if ($this->poolClearer->hasPool($id)) {
                $pools[$id] = $id;
            } else {
                $pool = $kernel->getContainer()->get($id);

                if ($pool instanceof CacheItemPoolInterface) {
                    $pools[$id] = $pool;
                } elseif ($pool instanceof Psr6CacheClearer) {
                    $clearers[$id] = $pool;
                } else {
                    throw new InvalidArgumentException(sprintf('"%s" is not a cache pool nor a cache clearer.', $id));
                }
            }
        }

        foreach ($clearers as $id => $clearer) {
            $io->comment(sprintf('Calling cache clearer: <info>%s</info>', $id));
            $clearer->clear($kernel->getContainer()->getParameter('kernel.cache_dir'));
        }

        $failure = false;
        foreach ($pools as $id => $pool) {
            $io->comment(sprintf('Clearing cache pool: <info>%s</info>', $id));

            if ($pool instanceof CacheItemPoolInterface) {
                if (!$pool->clear()) {
                    $io->warning(sprintf('Cache pool "%s" could not be cleared.', $pool));
                    $failure = true;
                }
            } else {
                if (false === $this->poolClearer->clearPool($id)) {
                    $io->warning(sprintf('Cache pool "%s" could not be cleared.', $pool));
                    $failure = true;
                }
            }
        }

        if ($failure) {
            return 1;
        }

        $io->success('Cache was successfully cleared.');

        return 0;
    }

    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if (\is_array($this->poolNames) && $input->mustSuggestArgumentValuesFor('pools')) {
            $suggestions->suggestValues($this->poolNames);
        }
    }
}
