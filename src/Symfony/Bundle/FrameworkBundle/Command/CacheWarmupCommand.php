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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Dumper\Preloader;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerAggregate;

/**
 * Warmup the cache.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final
 */
#[AsCommand(name: 'cache:warmup', description: 'Warm up an empty cache')]
class CacheWarmupCommand extends Command
{
    private CacheWarmerAggregate $cacheWarmer;

    public function __construct(CacheWarmerAggregate $cacheWarmer)
    {
        parent::__construct();

        $this->cacheWarmer = $cacheWarmer;
    }

    protected function configure()
    {
        $this
            ->setDefinition([
                new InputOption('no-optional-warmers', '', InputOption::VALUE_NONE, 'Skip optional cache warmers (faster)'),
            ])
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command warms up the cache.

Before running this command, the cache must be empty.

EOF
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $kernel = $this->getApplication()->getKernel();
        $io->comment(sprintf('Warming up the cache for the <info>%s</info> environment with debug <info>%s</info>', $kernel->getEnvironment(), var_export($kernel->isDebug(), true)));

        if (!$input->getOption('no-optional-warmers')) {
            $this->cacheWarmer->enableOptionalWarmers();
        }

        $preload = $this->cacheWarmer->warmUp($cacheDir = $kernel->getContainer()->getParameter('kernel.cache_dir'));

        if ($preload && file_exists($preloadFile = $cacheDir.'/'.$kernel->getContainer()->getParameter('kernel.container_class').'.preload.php')) {
            Preloader::append($preloadFile, $preload);
        }

        $io->success(sprintf('Cache for the "%s" environment (debug=%s) was successfully warmed.', $kernel->getEnvironment(), var_export($kernel->isDebug(), true)));

        return 0;
    }
}
