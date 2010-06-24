<?php

namespace Symfony\Framework\FoundationBundle\Command;

use Symfony\Components\Console\Input\InputArgument;
use Symfony\Components\Console\Input\InputOption;
use Symfony\Components\Console\Input\InputInterface;
use Symfony\Components\Console\Output\OutputInterface;
use Symfony\Components\Console\Output\Output;
use Symfony\Framework\FoundationBundle\Util\Filesystem;
use Symfony\Framework\FoundationBundle\Util\Mustache;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Initializes a new bundle.
 *
 * @package    Symfony
 * @subpackage Framework_FoundationBundle
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
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

        $dirs = $this->container->getKernelService()->getBundleDirs();

        $tmp = str_replace('\\', '/', $namespace);
        $namespace = str_replace('/', '\\', dirname($tmp));
        $bundle = basename($tmp);

        if (!isset($dirs[$namespace])) {
            throw new \InvalidArgumentException(sprintf('Unable to initialize the bundle (%s not defined).', $namespace));
        }

        $dir = $dirs[$namespace];
        $output->writeln(sprintf('Initializing bundle "<info>%s</info>" in "<info>%s</info>"', $bundle, realpath($dir)));

        if (file_exists($targetDir = $dir.'/'.$bundle)) {
            throw new \RuntimeException(sprintf('Bundle "%s" already exists.', $bundle));
        }

        $filesystem = new Filesystem();
        $filesystem->mirror(__DIR__.'/../Resources/skeleton/bundle', $targetDir);

        Mustache::renderDir($targetDir, array(
            'namespace' => $namespace,
            'bundle'    => $bundle,
        ));

        rename($targetDir.'/Bundle.php', $targetDir.'/'.$bundle.'.php');
    }
}
