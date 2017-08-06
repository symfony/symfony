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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\CacheClearer\Psr6CacheClearer;

/**
 * Clear cache pools.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class CachePoolClearCommand extends ContainerAwareCommand
{
    private $poolClearer;

    /**
     * @param Psr6CacheClearer $poolClearer
     */
    public function __construct($poolClearer = null)
    {
        parent::__construct();

        if (!$poolClearer instanceof Psr6CacheClearer) {
            @trigger_error(sprintf('Passing a command name as the first argument of "%s" is deprecated since version 3.4 and will be removed in 4.0. If the command was registered by convention, make it a service instead.', __METHOD__), E_USER_DEPRECATED);

            $this->setName(null === $poolClearer ? 'cache:pool:clear' : $poolClearer);

            return;
        }

        $this->poolClearer = $poolClearer;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('cache:pool:clear')
            ->setDefinition(array(
                new InputArgument('pools', InputArgument::IS_ARRAY | InputArgument::REQUIRED, 'A list of cache pools or cache pool clearers'),
            ))
            ->setDescription('Clears cache pools')
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
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // BC to be removed in 4.0
        if (null === $this->poolClearer) {
            $this->poolClearer = $this->getContainer()->get('cache.global_clearer');
            $cacheDir = $this->getContainer()->getParameter('kernel.cache_dir');
        }

        $io = new SymfonyStyle($input, $output);
        $kernel = $this->getApplication()->getKernel();
        $pools = array();
        $clearers = array();

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
                    throw new \InvalidArgumentException(sprintf('"%s" is not a cache pool nor a cache clearer.', $id));
                }
            }
        }

        foreach ($clearers as $id => $clearer) {
            $io->comment(sprintf('Calling cache clearer: <info>%s</info>', $id));
            $clearer->clear(isset($cacheDir) ? $cacheDir : $kernel->getContainer()->getParameter('kernel.cache_dir'));
        }

        foreach ($pools as $id => $pool) {
            $io->comment(sprintf('Clearing cache pool: <info>%s</info>', $id));

            if ($pool instanceof CacheItemPoolInterface) {
                $pool->clear();
            } else {
                $this->poolClearer->clearPool($id);
            }
        }

        $io->success('Cache was successfully cleared.');
    }
}
