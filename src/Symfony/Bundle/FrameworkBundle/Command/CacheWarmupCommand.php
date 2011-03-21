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
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Finder\Finder;

/**
 * Warmup the cache.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Francis Besset <francis.besset@gmail.com>
 */
class CacheWarmupCommand extends Command
{
    protected $cacheDir;
    protected $kernelTmp;

    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setName('cache:warmup')
            ->setDescription('Warms up an empty cache')
            ->setDefinition(array(
                new InputOption('warmup-dir', '', InputOption::VALUE_OPTIONAL, 'Warms up the cache in a specific directory')
            ))
            ->setHelp(<<<EOF
The <info>cache:warmup --warmup-dir=new_cache</info> command warms up the cache.

Before running this command, the cache must be empty if not use warmup-dir option.
EOF
            )
        ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);

        if ($input->hasOption('warmup-dir')) {
            $this->cacheDir = $input->getOption('warmup-dir');
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Warming up the cache');

        if (!$this->cacheDir) {
            $this->warmUp($this->container);
        } else {
            $class = get_class($this->container->get('kernel'));
            $this->kernelTmp = new $class(
                $this->container->getParameter('kernel.environment'),
                $this->container->getParameter('kernel.debug'),
                $this->cacheDir
            );

            $this->container->get('filesystem')->remove($this->kernelTmp->getCacheDir());

            $this->kernelTmp->boot();
            unlink($this->kernelTmp->getCacheDir().DIRECTORY_SEPARATOR.$this->kernelTmp->getContainerClass().'.php');

            $this->warmUp($this->kernelTmp->getContainer());
        }
    }

    protected function warmUp(ContainerInterface $container)
    {
        $warmer = $container->get('cache_warmer');
        $warmer->enableOptionalWarmers();
        $warmer->warmUp($container->getParameter('kernel.cache_dir'));
    }
}
