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
 * @author Kris Wallsmith <kris@symfony.com>
 */
class DumpCommand extends Command
{
    private $basePath;
    private $am;

    protected function configure()
    {
        $this
            ->setName('assetic:dump')
            ->setDescription('Dumps all assets to the filesystem')
            ->addArgument('write_to', InputArgument::OPTIONAL, 'Override the configured asset root')
            ->addOption('watch', null, InputOption::VALUE_NONE, 'Check for changes every second, debug mode only')
        ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);

        $this->basePath = $input->getArgument('write_to') ?: $this->container->getParameter('assetic.write_to');
        $this->am = $this->container->get('assetic.asset_manager');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$input->getOption('watch')) {
            foreach ($this->am->getNames() as $name) {
                $this->dumpAsset($name, $output);
            }

            return;
        }

        if (!$this->am->isDebug()) {
            throw new \RuntimeException('The --watch option is only available in debug mode.');
        }

        $this->watch($output);
    }

    /**
     * Watches a asset manager for changes.
     *
     * This method includes an infinite loop the continuously polls the asset
     * manager for changes.
     *
     * @param OutputInterface $output The command output
     */
    private function watch(OutputInterface $output)
    {
        $refl = new \ReflectionClass('Assetic\\AssetManager');
        $prop = $refl->getProperty('assets');
        $prop->setAccessible(true);

        $cache = sys_get_temp_dir().'/assetic_watch_'.substr(sha1($this->basePath), 0, 7);
        if (file_exists($cache)) {
            $previously = unserialize(file_get_contents($cache));
        } else {
            $previously = array();
        }

        $error = '';
        while (true) {
            try {
                foreach ($this->am->getNames() as $name) {
                    if ($asset = $this->checkAsset($name, $previously)) {
                        $this->dumpAsset($asset, $output);
                    }
                }

                // reset the asset manager
                $prop->setValue($this->am, array());
                $this->am->load();

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
     * @param string $name        The asset name
     * @param array  &$previously An array of previous visits
     *
     * @return AssetInterface|Boolean The asset if it should be dumped
     */
    private function checkAsset($name, array &$previously)
    {
        $formula = $this->am->hasFormula($name) ? serialize($this->am->getFormula($name)) : null;
        $asset = $this->am->get($name);
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
     * If the application or asset is in debug mode, each leaf asset will be
     * dumped as well.
     *
     * @param string          $name   An asset name
     * @param OutputInterface $output The command output
     */
    private function dumpAsset($name, OutputInterface $output)
    {
        $asset = $this->am->get($name);
        $formula = $this->am->getFormula($name);

        // start by dumping the main asset
        $this->doDump($asset, $output);

        // dump each leaf if debug
        if (isset($formula[2]['debug']) ? $formula[2]['debug'] : $this->am->isDebug()) {
            foreach ($asset as $leaf) {
                $this->doDump($leaf, $output);
            }
        }
    }

    /**
     * Performs the asset dump.
     *
     * @param AssetInterface  $asset  An asset
     * @param OutputInterface $output The command output
     *
     * @throws RuntimeException If there is a problem writing the asset
     */
    private function doDump(AssetInterface $asset, OutputInterface $output)
    {
        $target = rtrim($this->basePath, '/').'/'.str_replace('_controller/', '', $asset->getTargetPath());
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
