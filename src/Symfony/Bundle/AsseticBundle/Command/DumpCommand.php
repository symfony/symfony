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
use Symfony\Component\EventDispatcher\Event;

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

        // notify an event so custom stream wrappers can be registered lazily
        $this->container->get('event_dispatcher')->notify(new Event(null, 'assetic.write', array('path' => $basePath)));

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
     * manager for changes. If available, inotify is used to wait for changes.
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

        if (function_exists('inotify_init')) {
            $inotify = inotify_init();
        } else {
            $inotify = false;
        }

        $error = '';
        while (true) {
            try {
                file_put_contents($cache, serialize($previously));

                if (false !== $inotify) {
                    $reload = $this->inotifyWait($am, $output, $inotify);
                } else {
                    sleep(1);
                    $reload = true;
                }

                $checkAssets = array();
                if (true === $reload) {
                    // reset the asset manager
                    $prop->setValue($am, array());
                    $am->load();

                    $checkAssets = $am->getNames();
                } else if (is_array($reload)) {
                    $checkAssets = $reload;
                }

                foreach ($checkAssets as $name) {
                    if ($asset = $this->checkAsset($am, $name, $previously)) {
                        $this->dumpAsset($asset, $basePath, $output);
                    }
                }

                $error = '';
            } catch (\Exception $e) {
                if ($error != $msg = $e->getMessage()) {
                    $output->writeln('<error>[error]</error> '.$msg);
                    $error = $msg;
                }
            }
        }

        if (false !== $inotify) {
            fclose($inotify);
        }
    }

    /**
     * Sets up watches for inotify to monitor all assetic resources and assets.
     *
     * After waiting on inotify to find file modifications it either returns an
     * array of modified assets or true to indicate that a resource was modifed.
     *
     * @param LazyAssetManager $am      The asset manager
     * @param OutputInterface  $output  The command output
     * @param resource         $inotify File descriptor of the inotify handle
     *
     * @return Boolean|array True if a resource was modified or an array of
     *                       modified assets.
     */
    protected function inotifyWait(LazyAssetManager $am, OutputInterface $output, $inotify)
    {
        $flags = IN_MODIFY | IN_ATTRIB | IN_MOVE | IN_CREATE | IN_DELETE | IN_DELETE_SELF | IN_MOVE_SELF;

        // add a watch for every resource (e.g. template file directory)
        $resourceWatches = array();
        foreach ($am->getResources() as $resource) {
            $path = (string) $resource;
            $watch = inotify_add_watch($inotify, $path, $flags);
            $resourceWatches[$watch] = true;
        }

        // add watches for all files involved in any formulas
        $assetsWatches = array();
        foreach ($am->getNames() as $name) {
            $asset = $am->get($name);
            foreach ($asset->getPaths() as $path) {
                $watch = inotify_add_watch($inotify, $path, $flags);
                $assetWatches[$watch] = $name;
            }
        }

        // blocks until an event occurs
        $events = inotify_read($inotify);

        $reloadAssets = array();
        $reload = false;
        foreach ($events as $event) {
            if (isset($resourceWatches[$event['wd']])) {
                $reload = true;
            } else if (isset($assetWatches[$event['wd']])) {
                $reloadAssets[$event['wd']] = $assetWatches[$event['wd']];
            }
        }

        if ($reload) {
            $output->writeln('<info>[reload]</info> all asset manager resources');
            return true;
        }

        return $reloadAssets;
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
