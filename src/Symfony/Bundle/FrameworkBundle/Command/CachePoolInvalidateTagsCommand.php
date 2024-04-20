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

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Contracts\Service\ServiceProviderInterface;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
#[AsCommand(name: 'cache:pool:invalidate-tags', description: 'Invalidate cache tags for all or a specific pool')]
final class CachePoolInvalidateTagsCommand extends Command
{
    private array $poolNames;

    public function __construct(
        private ServiceProviderInterface $pools,
    ) {
        parent::__construct();

        $this->poolNames = array_keys($pools->getProvidedServices());
    }

    protected function configure(): void
    {
        $this
            ->addArgument('tags', InputArgument::IS_ARRAY | InputArgument::REQUIRED, 'The tags to invalidate')
            ->addOption('pool', 'p', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'The pools to invalidate on')
            ->setHelp(<<<'EOF'
                The <info>%command.name%</info> command invalidates tags from taggable pools. By default, all pools
                have the passed tags invalidated. Pass <info>--pool=my_pool</info> to invalidate tags on a specific pool.

                  php %command.full_name% tag1 tag2
                  php %command.full_name% tag1 tag2 --pool=cache2 --pool=cache1
                EOF)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $pools = $input->getOption('pool') ?: $this->poolNames;
        $tags = $input->getArgument('tags');
        $tagList = implode(', ', $tags);
        $errors = false;

        foreach ($pools as $name) {
            $io->comment(sprintf('Invalidating tag(s): <info>%s</info> from pool <comment>%s</comment>.', $tagList, $name));

            try {
                $pool = $this->pools->get($name);
            } catch (ServiceNotFoundException) {
                $io->error(sprintf('Pool "%s" not found.', $name));
                $errors = true;

                continue;
            }

            if (!$pool instanceof TagAwareCacheInterface) {
                $io->error(sprintf('Pool "%s" is not taggable.', $name));
                $errors = true;

                continue;
            }

            if (!$pool->invalidateTags($tags)) {
                $io->error(sprintf('Cache tag(s) "%s" could not be invalidated for pool "%s".', $tagList, $name));
                $errors = true;
            }
        }

        if ($errors) {
            $io->error('Done but with errors.');

            return 1;
        }

        $io->success('Successfully invalidated cache tags.');

        return 0;
    }

    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestOptionValuesFor('pool')) {
            $suggestions->suggestValues($this->poolNames);
        }
    }
}
