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
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerAggregate;

/**
 * Warmup the cache.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final since version 3.4
 */
class CacheWarmupCommand extends ContainerAwareCommand
{
    protected static $defaultName = 'cache:warmup';

    private $cacheWarmer;

    /**
     * @param CacheWarmerAggregate $cacheWarmer
     */
    public function __construct($cacheWarmer = null)
    {
        if (!$cacheWarmer instanceof CacheWarmerAggregate) {
            @trigger_error(sprintf('Passing a command name as the first argument of "%s" is deprecated since version 3.4 and will be removed in 4.0. If the command was registered by convention, make it a service instead.', __METHOD__), E_USER_DEPRECATED);

            parent::__construct($cacheWarmer);

            return;
        }

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
        // BC to be removed in 4.0
        if (null === $this->cacheWarmer) {
            $this->cacheWarmer = $this->getContainer()->get('cache_warmer');
            $cacheDir = $this->getContainer()->getParameter('kernel.cache_dir');
        }

        $io = new SymfonyStyle($input, $output);

        $kernel = $this->getApplication()->getKernel();
        $io->comment(sprintf('Warming up the cache for the <info>%s</info> environment with debug <info>%s</info>', $kernel->getEnvironment(), var_export($kernel->isDebug(), true)));

        if (!$input->getOption('no-optional-warmers')) {
            $this->cacheWarmer->enableOptionalWarmers();
        }

        $this->cacheWarmer->warmUp(isset($cacheDir) ? $cacheDir : $kernel->getContainer()->getParameter('kernel.cache_dir'));

        $io->success(sprintf('Cache for the "%s" environment (debug=%s) was successfully warmed.', $kernel->getEnvironment(), var_export($kernel->isDebug(), true)));
    }
}
