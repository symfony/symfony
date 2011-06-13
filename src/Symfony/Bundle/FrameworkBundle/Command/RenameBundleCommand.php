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
 * Initializes a new bundle.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class RenameBundleCommand extends Command
{
    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setName('bundle:rename')
            ->setDescription('Rename existing bundle')
            ->addArgument('namespace', InputArgument::REQUIRED, 'Namespace of the bundle, vendor name')
            ->addArgument('bundleName', InputArgument::REQUIRED, 'The name of the bundle to be renamed <comment>(without "Bundle" sufix!)</comment>')
            ->addArgument('newBundleName', InputArgument::REQUIRED, 'New name of the bundle <comment>(without "Bundle" sufix!)</comment>')
            ->addOption('confirm', null, InputOption::VALUE_OPTIONAL, 'Confirm with <info>--confirm</info> that you want to rename the bundle else it\'s a dry run')
            ->setHelp(<<<EOT
The <info>bundle:rename</info> command will <comment>rename</comment> a bundle.

./app/console bundle:rename "<comment>Vendor</comment>" "<info>Old</info>" "<info>New</info>"

At this moment you can only change the bundle name not the vendor as well so
please use the same vendor name when creating the new bundle.

<error>Routes and other resources will be modified</error> as well if they are present in the bundle directory
else <error>only</error> the <comment>AppKernel.php</comment> file will be changed outside of the bundle directory.

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
        $vendor = $input->getArgument('namespace');
        $bundleName = $input->getArgument('bundleName');
        $newBundleName = $input->getArgument('newBundleName');

        $dryRun = true === $input->hasParameterOption('--confirm') ? false : true;

        $bundle = $this->getApplication()->getKernel()->getBundle($vendor.$bundleName.'Bundle');

        $path = $bundle->getPath();
        $appDir = realpath($path.'/../../../').'/app/';

        $output->writeln(sprintf('This operation is in <info>%s</info> mode.', $dryRun ? 'DRY RUN' : 'EXECUTION'));

        $currentLevel = error_reporting();
        error_reporting($currentLevel ^ E_WARNING);

        $newPath = realpath($path.'/../').'/'.$newBundleName.'Bundle';

        if(!$dryRun)
        {
            if(rename($path, $newPath))
            {
                $output->writeln('Bundle <comment>'.$bundleName.'</comment> directory changed to <comment>'.$newBundleName.'</comment> <info>successfully</info>');
            }
            else
            {
                $output->writeln('Bundle <comment>'.$bundleName.'</comment> directory <error>couldn\'t</error> be changed to '.$newBundleName);
                $output->writeln('Aborting automated bundle renaming...');

                return;
            }
        }

        $searchPath = $dryRun ? $path : $newPath.'/';

        $finder = new Finder();
        $finder->files()->name('/\.(php|yml|inc|xml)$/')->in($searchPath);

        foreach($finder as $file)
        {
            $fileName = $file->getRealpath();
            $this->alterFileContents($output, $dryRun, $bundleName, $newBundleName, $fileName, $searchPath);
        }

        if(!$dryRun)
        {
            if(file_exists($newPath.'/'.$vendor.$bundleName.'Bundle.php') && rename($newPath.'/'.$vendor.$bundleName.'Bundle.php', $newPath.'/'.$vendor.$newBundleName.'Bundle.php'))
            {
                $output->writeln('-><comment>'.$vendor.$bundleName.'.php</comment> was <info>renamed</info> to <comment>'.$vendor.$newBundleName.'Bundle.php</comment>');
            }
            else
            {
                $output->writeln('-><comment>'.$vendor.$newBundleName.'.php</comment> <error>couldn\'t</error> be renamed');
            }
        }

        error_reporting($currentLevel);

        $kernelFile = $appDir.'AppKernel.php';
        $this->alterFileContents($output, $dryRun, $bundleName, $newBundleName, $kernelFile, $appDir);
    }

    private function alterFileContents(OutputInterface $output, $dryRun, $bundleName, $newBundleName, $fileName, $searchPath)
    {
        if(!$dryRun)
        {
            $content = str_replace($bundleName.'Bundle', $newBundleName.'Bundle', file_get_contents($fileName));
            if(false !== file_put_contents($fileName, $content))
            {
                $fileName = '<comment>'.str_replace($searchPath, '', $fileName).'</comment>';
                $output->writeln('->'.$fileName.' was <info>altered</info>');
            }
            else
            {
                $fileName = '<comment>'.str_replace($searchPath, '', $fileName).'</comment>';
                $output->writeln('->'.$fileName.' <error>couln\'t</error> be altered');
            }
        }
        else
        {
            $fileName = '<comment>'.str_replace($searchPath, '', $fileName).'</comment>';
            $output->writeln('->'.$fileName.' will be <comment>altered</comment>');
        }

    }
}


















