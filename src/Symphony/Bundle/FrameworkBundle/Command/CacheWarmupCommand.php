<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\FrameworkBundle\Command;

use Symphony\Component\Console\Command\Command;
use Symphony\Component\Console\Input\InputInterface;
use Symphony\Component\Console\Input\InputOption;
use Symphony\Component\Console\Output\OutputInterface;
use Symphony\Component\Console\Style\SymphonyStyle;
use Symphony\Component\HttpKernel\CacheWarmer\CacheWarmerAggregate;

/**
 * Warmup the cache.
 *
 * @author Fabien Potencier <fabien@symphony.com>
 *
 * @final
 */
class CacheWarmupCommand extends Command
{
    protected static $defaultName = 'cache:warmup';

    private $cacheWarmer;

    public function __construct(CacheWarmerAggregate $cacheWarmer)
    {
        parent::__construct();

        $this->cacheWarmer = $cacheWarmer;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDefinition(array(
                new InputOption('no-optional-warmers', '', InputOption::VALUE_NONE, 'Skip optional cache warmers (faster)'),
            ))
            ->setDescription('Warms up an empty cache')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command warms up the cache.

Before running this command, the cache must be empty.

This command does not generate the classes cache (as when executing this
command, too many classes that should be part of the cache are already loaded
in memory). Use <comment>curl</comment> or any other similar tool to warm up
the classes cache if you want.

EOF
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymphonyStyle($input, $output);

        $kernel = $this->getApplication()->getKernel();
        $io->comment(sprintf('Warming up the cache for the <info>%s</info> environment with debug <info>%s</info>', $kernel->getEnvironment(), var_export($kernel->isDebug(), true)));

        if (!$input->getOption('no-optional-warmers')) {
            $this->cacheWarmer->enableOptionalWarmers();
        }

        $this->cacheWarmer->warmUp($kernel->getContainer()->getParameter('kernel.cache_dir'));

        $io->success(sprintf('Cache for the "%s" environment (debug=%s) was successfully warmed.', $kernel->getEnvironment(), var_export($kernel->isDebug(), true)));
    }
}
