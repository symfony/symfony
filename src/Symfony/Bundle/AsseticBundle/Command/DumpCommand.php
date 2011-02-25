<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Bundle\AsseticBundle\Command;

use Assetic\Asset\AssetInterface;
use Assetic\Factory\LazyAssetManager;
use Symfony\Bundle\FrameworkBundle\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Dumps assets to the filesystem.
 *
 * @author Kris Wallsmith <kris.wallsmith@symfony-project.com>
 */
class DumpCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('assetic:dump')
            ->setDescription('Dumps all assets to the filesystem')
            ->addArgument('base_dir', InputArgument::OPTIONAL, 'The base directory')
            ->addOption('watch', null, InputOption::VALUE_NONE, 'Check for changes every second')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$baseDir = $input->getArgument('base_dir')) {
            $baseDir = $this->container->getParameter('assetic.document_root');
        }

        $am = $this->container->get('assetic.asset_manager');

        if ($input->getOption('watch')) {
            return $this->watch($am, $baseDir, $output, $this->container->getParameter('kernel.debug'));
        }

        foreach ($am->getNames() as $name) {
            $this->dumpAsset($am->get($name), $baseDir, $output);
        }
    }

    /**
     * Watches a asset manager for changes.
     *
     * This method includes an infinite loop the continuously polls the asset
     * manager for changes.
     *
     * @param LazyAssetManager $am      The asset manager
     * @param string           $baseDir The base directory to write to
     * @param OutputInterface  $output  The command output
     * @param Boolean          $debug   Debug mode
     */
    protected function watch(LazyAssetManager $am, $baseDir, OutputInterface $output, $debug = false)
    {
        $previously = array();

        while (true) {
            // reload formulae when in debug
            if ($debug) {
                $am->load();
            }

            foreach ($am->getNames() as $name) {
                if ($asset = $this->checkAsset($am, $name, $previously)) {
                    $this->dumpAsset($asset, $baseDir, $output);
                }
            }

            sleep(1);
        }
    }

    /**
     * Checks if an asset should be dumped.
     *
     * @param LazyAssetManager $am         The asset manager
     * @param string           $name       The asset name
     * @param array            $previously An array of previous visits
     *
     * @return AssetInterface|Boolean The asset if it should be dumped
     */
    protected function checkAsset(LazyAssetManager $am, $name, array &$previously)
    {
        $formula = serialize($am->getFormula($name));
        $asset = $am->get($name);
        $mtime = $asset->getLastModified();

        if (isset($previously[$name])) {
            $changed = $previously[$name]['mtime'] != $mtime || $previously[$name]['formula'] != $formula;
        } else {
            $changed = true;
        }

        $previously[$name] = array('mtime' => $mtime, 'formula' => $formula);

        return $changed ? $asset : false;
    }

    /**
     * Writes an asset.
     *
     * @param AssetInterface  $asset   An asset
     * @param string          $baseDir The base directory to write to
     * @param OutputInterface $output  The command output
     *
     * @throws RuntimeException If there is a problem writing the asset
     */
    protected function dumpAsset(AssetInterface $asset, $baseDir, OutputInterface $output)
    {
        $target = rtrim($baseDir, '/') . '/' . $asset->getTargetUrl();
        if (!is_dir($dir = dirname($target))) {
            $output->writeln('<info>[dir+]</info> '.$dir);
            if (false === @mkdir($dir)) {
                throw new \RuntimeException('Unable to create directory '.$dir);
            }
        }

        $output->writeln('<info>[file+]</info> '.$target);
        if (false === @file_put_contents($target, $asset->dump())) {
            throw new \RuntimeException('Unable to write file '.$target);
        }
    }
}
