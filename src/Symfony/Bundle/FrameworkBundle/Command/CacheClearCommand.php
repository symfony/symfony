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
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\ProcessBuilder;

/**
 * Clear and Warmup the cache.
 *
 * @author Francis Besset <francis.besset@gmail.com>
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Gábor Egyed <gabor.egyed@gmail.com>
 */
class CacheClearCommand extends ContainerAwareCommand
{
    /**
     * {@inheritdoc}
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
        $realCacheDir = $this->getContainer()->getParameter('kernel.cache_dir');
        $oldCacheDir = $realCacheDir.'_old';
        $filesystem = $this->getContainer()->get('filesystem');
        $ret = 0;

        if (!is_writable($realCacheDir)) {
            throw new \RuntimeException(sprintf('Unable to write in the "%s" directory', $realCacheDir));
        }

        if ($filesystem->exists($oldCacheDir)) {
            $filesystem->remove($oldCacheDir);
        }

        $kernel = $this->getContainer()->get('kernel');
        $output->writeln(sprintf('Clearing the cache for the <info>%s</info> environment with debug <info>%s</info>', $kernel->getEnvironment(), var_export($kernel->isDebug(), true)));
        $this->getContainer()->get('cache_clearer')->clear($realCacheDir);

        $filesystem->rename($realCacheDir, $oldCacheDir);

        if (!$input->getOption('no-warmup')) {
            $finder = new PhpExecutableFinder();
            $php = $finder->find();

            $pb = new ProcessBuilder();
            $pb
                ->inheritEnvironmentVariables(true)
                ->add($php)
                ->add($_SERVER['argv'][0])
                ->add('cache:warmup')
            ;

            // pass valid options only
            $options = array_intersect_key($input->getOptions(), array_merge(
                $this->getApplication()->getDefinition()->getOptions(),
                $this->getApplication()->get('cache:warmup')->getDefinition()->getOptions()
            ));

            foreach ($options as $option => $value) {
                if (!is_bool($value)) {
                    $pb->add(sprintf('--%s=%s', $option, $value));
                } elseif (true === $value) {
                    $pb->add(sprintf('--%s', $option));
                }
            }

            $process = $pb->getProcess();
            $process->run();

            if (0 !== $ret = $process->getExitCode()) {
                $output->writeln(sprintf('<error>Cache warmup terminated with an error status (%s)</error>', $ret));
            }
        }

        $filesystem->remove($oldCacheDir);

        return $ret;
    }
}
