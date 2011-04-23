<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
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
 * @author Kris Wallsmith <kris.wallsmith@symfony.com>
 */
class DumpCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('assetic:dump')
            ->setDescription('Dumps all assets to the filesystem')
            ->addArgument('write_to', InputArgument::OPTIONAL, 'Override the configured asset root')
            ->addOption('watch', null, InputOption::VALUE_NONE, 'Check for changes every second, debug mode only')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$basePath = $input->getArgument('write_to')) {
            $basePath = $this->container->getParameter('assetic.write_to');
        }

        $am = $this->container->get('assetic.asset_manager');

        if ($input->getOption('watch')) {
            return $this->watch($am, $basePath, $output, $this->container->getParameter('kernel.debug'));
        }

        foreach ($am->getNames() as $name) {
            $this->dumpAsset($am->get($name), $basePath, $output);
        }
    }

    /**
     * Watches a asset manager for changes.
     *
     * This method includes an infinite loop the continuously polls the asset
     * manager for changes.
     *
     * @param LazyAssetManager $am      The asset manager
     * @param string           $basePath The base directory to write to
     * @param OutputInterface  $output  The command output
     * @param Boolean          $debug   Debug mode
     */
    protected function watch(LazyAssetManager $am, $basePath, OutputInterface $output, $debug = false)
    {
        if (!$debug) {
            throw new \RuntimeException('The --watch option is only available in debug mode.');
        }

        $refl = new \ReflectionClass('Assetic\\AssetManager');
        $prop = $refl->getProperty('assets');
        $prop->setAccessible(true);

        $cache = sys_get_temp_dir().'/assetic_watch_'.substr(sha1($basePath), 0, 7);
        if (file_exists($cache)) {
            $previously = unserialize(file_get_contents($cache));
        } else {
            $previously = array();
        }

        $error = '';
        while (true) {
            try {
                foreach ($am->getNames() as $name) {
                    if ($asset = $this->checkAsset($am, $name, $previously)) {
                        $this->dumpAsset($asset, $basePath, $output);
                    }
                }

                // reset the asset manager
                $prop->setValue($am, array());
                $am->load();

                file_put_contents($cache, serialize($previously));
                $error = '';

                sleep(1);
            } catch (\Exception $e) {
                if ($error != $msg = $e->getMessage()) {
                    $output->writeln('<error>[error]</error> '.$msg);
                    $error = $msg;
                }
            }
        }
    }

    /**
     * Checks if an asset should be dumped.
     *
     * @param LazyAssetManager $am          The asset manager
     * @param string           $name        The asset name
     * @param array            &$previously An array of previous visits
     *
     * @return AssetInterface|Boolean The asset if it should be dumped
     */
    protected function checkAsset(LazyAssetManager $am, $name, array &$previously)
    {
        $formula = $am->hasFormula($name) ? serialize($am->getFormula($name)) : null;
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
     * @param string          $basePath The base directory to write to
     * @param OutputInterface $output  The command output
     *
     * @throws RuntimeException If there is a problem writing the asset
     */
    protected function dumpAsset(AssetInterface $asset, $basePath, OutputInterface $output)
    {
        $target = rtrim($basePath, '/') . '/' . $asset->getTargetUrl();
        if (!is_dir($dir = dirname($target))) {
            $output->writeln('<info>[dir+]</info> '.$dir);
            if (false === @mkdir($dir, 0777, true)) {
                throw new \RuntimeException('Unable to create directory '.$dir);
            }
        }

        $output->writeln('<info>[file+]</info> '.$target);
        if (false === @file_put_contents($target, $asset->dump())) {
            throw new \RuntimeException('Unable to write file '.$target);
        }
    }
}
