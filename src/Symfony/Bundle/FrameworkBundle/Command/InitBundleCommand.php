<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\Output;
use Symfony\Bundle\FrameworkBundle\Util\Mustache;

/**
 * Initializes a new bundle.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class InitBundleCommand extends Command
{
    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setDefinition(array(
                new InputArgument('namespace', InputArgument::REQUIRED, 'The namespace of the bundle to create'),
                new InputArgument('dir', InputArgument::REQUIRED, 'The directory where to create the bundle'),
            ))
            ->setName('init:bundle')
        ;
    }

    /**
     * @see Command
     *
     * @throws \InvalidArgumentException When namespace doesn't end with Bundle
     * @throws \RuntimeException         When bundle can't be executed
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!preg_match('/Bundle$/', $namespace = $input->getArgument('namespace'))) {
            throw new \InvalidArgumentException('The namespace must end with Bundle.');
        }

        $bundle = strtr($namespace, array('\\' => ''));

        $dir = $input->getArgument('dir');
        $targetDir = $dir . strtr($namespace, '\\', '/');
        $output->writeln(sprintf('Initializing bundle "<info>%s</info>" in "<info>%s</info>"', $bundle, $dir));

        if (file_exists($targetDir)) {
            throw new \RuntimeException(sprintf('Bundle "%s" already exists.', $bundle));
        }

        $filesystem = $this->container->get('filesystem');
        $filesystem->mirror(__DIR__.'/../Resources/skeleton/bundle', $targetDir);

        Mustache::renderDir($targetDir, array(
            'namespace' => $namespace,
            'bundle'    => $bundle,
        ));

        rename($targetDir.'/Bundle.php', $targetDir.'/'.$bundle.'.php');
    }
}
