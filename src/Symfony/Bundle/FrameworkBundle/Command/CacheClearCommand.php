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
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Finder\Finder;

/**
 * Clear and Warmup the cache.
 *
 * @author Francis Besset <francis.besset@gmail.com>
 * @author Fabien Potencier <fabien@symfony.com>
 */
class CacheClearCommand extends ContainerAwareCommand
{
    protected $name;

    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setName('cache:clear')
            ->setDefinition(array(
                new InputOption('no-warmup', '', InputOption::VALUE_NONE, 'Do not warm up the cache'),
                new InputOption('no-optional-warmers', '', InputOption::VALUE_NONE, 'Skip optional cache warmers (faster)'),
            ))
            ->setDescription('Clears the cache')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command clears the application cache for a given environment
and debug mode:

<info>php %command.full_name% --env=dev</info>
<info>php %command.full_name% --env=prod --no-debug</info>
EOF
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer();

        $cacheDir = $container->getParameter('kernel.cache_dir');

        if (!is_writable($cacheDir)) {
            throw new \RuntimeException(sprintf('Unable to write in the "%s" directory', $cacheDir));
        }

        $kernel = $container->get('kernel');
        $output->writeln(sprintf('Clearing the cache for the <info>%s</info> environment with debug <info>%s</info>', $kernel->getEnvironment(), var_export($kernel->isDebug(), true)));

        $container->get('cache_clearer')->clear($cacheDir);

        if ($input->getOption('no-warmup')) {
            $oldCacheDir  = $cacheDir.'__old__';
            rename($cacheDir, $oldCacheDir);
            $container->get('filesystem')->remove($oldCacheDir);
        } else {
            $optionalEnabled = !$input->getOption('no-optional-warmers');
            $warmer = $container->get('cache_warmer');
            $warmer->warmUpForEnv($kernel, $container->get('filesystem'), $cacheDir, $kernel->getEnvironment(), $kernel->isDebug(), $optionalEnabled);
        }
    }
}
