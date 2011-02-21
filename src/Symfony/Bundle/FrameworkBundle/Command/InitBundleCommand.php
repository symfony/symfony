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
                new InputArgument('bundleName', InputArgument::OPTIONAL, 'The optional bundle name'),
                new InputOption('format', '', InputOption::VALUE_REQUIRED, 'Use the format for configuration files (php, xml, or yml)', 'yml')
            ))
            ->setHelp(<<<EOT
The <info>init:bundle</info> command generates a new bundle with a basic skeleton.

<info>./app/console init:bundle "Vendor\HelloBundle" src [bundleName]</info>

The bundle namespace must end with "Bundle" (e.g. <comment>Vendor\HelloBundle</comment>)
and can be placed in any directory (e.g. <comment>src</comment>).

If you don't specify a bundle name (e.g. <comment>HelloBundle</comment>), the bundle name will
be the concatenation of the namespace segments (e.g. <comment>VendorHelloBundle</comment>).
EOT
            )
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

        // validate namespace
        if (preg_match('/[^A-Za-z0-9_\\\-]/', $namespace)) {
            throw new \InvalidArgumentException('The namespace contains invalid characters.');
        }

        // user specified bundle name?
        $bundle = $input->getArgument('bundleName');
        if (!$bundle) {
            $bundle = strtr($namespace, array('\\' => ''));
        }

        if (!preg_match('/Bundle$/', $bundle)) {
            throw new \InvalidArgumentException('The bundle name must end with Bundle.');
        }

        $dir = $input->getArgument('dir');

        // add trailing / if necessary
        $dir = '/' === substr($dir, -1, 1) ? $dir : $dir.'/';

        $targetDir = $dir.strtr($namespace, '\\', '/');

        $output->writeln(sprintf('Initializing bundle "<info>%s</info>" in "<info>%s</info>"', $bundle, $dir));

        if (file_exists($targetDir)) {
            throw new \RuntimeException(sprintf('Bundle "%s" already exists.', $bundle));
        }

        $filesystem = $this->container->get('filesystem');
        $filesystem->mirror(__DIR__.'/../Resources/skeleton/bundle/generic', $targetDir);
        $filesystem->mirror(__DIR__.'/../Resources/skeleton/bundle/'.$input->getOption('format'), $targetDir);

        Mustache::renderDir($targetDir, array(
            'namespace' => $namespace,
            'bundle'    => $bundle,
        ));

        rename($targetDir.'/Bundle.php', $targetDir.'/'.$bundle.'.php');
    }
}
