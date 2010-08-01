<?php

namespace Symfony\Bundle\FrameworkBundle\Command;

use Symfony\Components\Console\Command\Command as BaseCommand;
use Symfony\Components\Console\Input\InputArgument;
use Symfony\Components\Console\Input\InputOption;
use Symfony\Components\Console\Input\InputInterface;
use Symfony\Components\Console\Output\OutputInterface;
use Symfony\Components\Console\Output\Output;
use Symfony\Bundle\FrameworkBundle\Util\Filesystem;
use Symfony\Bundle\FrameworkBundle\Util\Mustache;

/*
 * This file is part of the symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Initializes a new application.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class InitApplicationCommand extends BaseCommand
{
    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setDefinition(array(
                new InputArgument('name', InputArgument::REQUIRED, 'The application name (Hello)'),
                new InputArgument('path', InputArgument::REQUIRED, 'The path to the application (hello/)'),
                new InputArgument('web_path', InputArgument::REQUIRED, 'The path to the public web root (web/)'),
                new InputOption('format', '', InputOption::PARAMETER_REQUIRED, 'Use the format for configuration files (php, xml, or yaml)', 'xml'),
            ))
            ->setName('init:application')
        ;
    }

    /**
     * @see Command
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (file_exists($targetDir = $input->getArgument('path'))) {
            throw new \RuntimeException(sprintf('The directory "%s" already exists.', $targetDir));
        }

        if (!file_exists($webDir = $input->getArgument('web_path'))) {
            mkdir($webDir, 0777, true);
        }

        $parameters = array(
            'class'       => $input->getArgument('name'),
            'application' => strtolower($input->getArgument('name')),
            'format'      => $input->getOption('format'),
        );

        $filesystem = new Filesystem();

        $filesystem->mirror(__DIR__.'/../Resources/skeleton/application/generic', $targetDir);
        $filesystem->mirror(__DIR__.'/../Resources/skeleton/application/'.$input->getOption('format'), $targetDir);
        Mustache::renderDir($targetDir, $parameters);
        $filesystem->chmod($targetDir.'/console', 0777);
        $filesystem->chmod($targetDir.'/logs', 0777);
        $filesystem->chmod($targetDir.'/cache', 0777);

        $filesystem->rename($targetDir.'/Kernel.php', $targetDir.'/'.$input->getArgument('name').'Kernel.php');
        $filesystem->rename($targetDir.'/Cache.php', $targetDir.'/'.$input->getArgument('name').'Cache.php');

        $filesystem->copy(__DIR__.'/../Resources/skeleton/web/front_controller.php', $file = $webDir.'/'.(file_exists($webDir.'/index.php') ? strtolower($input->getArgument('name')) : 'index').'.php');
        Mustache::renderFile($file, $parameters);

        $filesystem->copy(__DIR__.'/../Resources/skeleton/web/front_controller_debug.php', $file = $webDir.'/'.strtolower($input->getArgument('name')).'_dev.php');
        Mustache::renderFile($file, $parameters);
    }
}
