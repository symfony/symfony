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

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\Output;
use Symfony\Bundle\FrameworkBundle\Generator\Generator;
use Symfony\Component\Finder\Finder;

/**
 * Delete exiting bundle.
 *
 * @author Florin Patan <florinpatan@gmail.com>
 */
class DeleteBundleCommand extends Command
{
    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setName('bundle:delete')
            ->setDescription('Delete existing bundle')
            ->addArgument('bundleName', InputArgument::REQUIRED, 'The name of the bundle to be deleted')
            ->addOption('confirm', null, InputOption::VALUE_OPTIONAL, 'Confirm with <info>--confirm</info> that you want to delete the bundle else it\'s a dry run')
            ->setHelp(<<<EOT
The <info>bundle:delete</info> command will <error>delete</error> a bundle from the existing ones.

./app/console bundle:delete <info>"VendorHelloBundle"</info>
EOT
            )
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
        $bundleName = $input->getArgument('bundleName');

        $bundle = $this->getApplication()->getKernel()->getBundle($bundleName);

        $dryRun = true === $input->hasParameterOption('--confirm') ? false : true;

        $path = $bundle->getPath();

        $output->writeln(sprintf('This operation is in <info>%s</info> mode.', $dryRun ? 'DRY RUN' : 'EXECUTION'));
        $output->writeln('The following files and directories will be <error>deleted</error>.');

        $finder = new Finder();
        $finder->files()->in($path);

        $currentLevel = error_reporting();

        error_reporting($currentLevel ^ E_WARNING);

        $errors = array();

        foreach($finder as $file)
        {
            $filePath = $file->getRealpath();
            $result = '';
            if(!$dryRun)
            {
                if(unlink($filePath))
                {
                    $result = 'deleted <info>successfully</info>';
                }
                else
                {
                    $result = '<error>couldn\'t be deleted</error>';
                    $errors[] = $filePath;
                }
            }
            $output->writeln(sprintf("->%s %s", $filePath, $result));
        }

        $finder->directories()->in($path);
        $directoryPaths = array();
        foreach($finder as $directory)
        {
            $directoryPaths[] = $directory->getRealpath();
        }

        $directoryPaths = array_reverse($directoryPaths);
        $lastDir = array_pop($directoryPaths);
        $appDir = realpath($lastDir.'/../../../../app/').'/';
        array_push($directoryPaths, realpath($lastDir.'/../'));
        $directoryPaths = array_unique($directoryPaths);
        foreach($directoryPaths as $directoryPath)
        {
            $result = '';
            if(!$dryRun)
            {
                if(rmdir($directoryPath))
                {
                    $result = 'deleted <info>successfully</info>';
                }
                else
                {
                    $result = '<error>couldn\'t be deleted</error>';
                    $errors[] = $directoryPath;
                }
            }
            $output->writeln(sprintf("->%s %s", $directoryPath, $result));
        }

        error_reporting($currentLevel);

        $kernelFile = $appDir.'AppKernel.php';
        $lines = file($kernelFile);
        $lineNumber = 0;
        foreach($lines as $line)
        {
            if(false !== strpos($line, $bundleName))
            {
                array_splice($lines, $lineNumber, 1);
                $lineNumber--;
            }

            $lineNumber++;
        }
        if(!$dryRun)
        {
            file_put_contents($kernelFile, implode('', $lines));
        }

        if(count($errors) > 0)
        {
            $output->writeln("\nErrors where encountered while trying to remove the bundle: " . $bundleName);
            foreach($errors as $error)
            {
                $output->writeln("<error>-></error>".$error);
            }
        }
    }
}
