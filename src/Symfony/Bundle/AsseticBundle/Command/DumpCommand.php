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
            ->addOption('force', null, InputOption::VALUE_NONE, 'Force an initial generation of all assets (used with --watch)')
            ->addOption('period', null, InputOption::VALUE_REQUIRED, 'Set the polling period in seconds (used with --watch)', 1)
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
        $output->writeln(sprintf('Dumping all <comment>%s</comment> assets.', $input->getOption('env')));
        $output->writeln(sprintf('Debug mode is <comment>%s</comment>.', $input->getOption('no-debug') ? 'off' : 'on'));
        $output->writeln('');

        if (!$input->getOption('watch')) {
            foreach ($this->am->getNames() as $name) {
                $this->dumpAsset($name, $output);
            }

            return;
        }

        if (!$this->am->isDebug()) {
            throw new \RuntimeException('The --watch option is only available in debug mode.');
        }

        $this->watch($input, $output);
    }

    /**
     * Watches a asset manager for changes.
     *
     * This method includes an infinite loop the continuously polls the asset
     * manager for changes.
     *
     * @param InputInterface  $input  The command input
     * @param OutputInterface $output The command output
     */
    private function watch(InputInterface $input, OutputInterface $output)
    {
        $refl = new \ReflectionClass('Assetic\\AssetManager');
        $prop = $refl->getProperty('assets');
        $prop->setAccessible(true);

        $cache = sys_get_temp_dir().'/assetic_watch_'.substr(sha1($this->basePath), 0, 7);
        if ($input->getOption('force') || !file_exists($cache)) {
            $previously = array();
        } else {
            $previously = unserialize(file_get_contents($cache));
        }

        $error = '';
        while (true) {
            try {
                foreach ($this->am->getNames() as $name) {
                    if ($this->checkAsset($name, $previously)) {
                        $this->dumpAsset($name, $output);
                    }
                }

                // reset the asset manager
                $prop->setValue($this->am, array());
                $this->am->load();

                file_put_contents($cache, serialize($previously));
                $error = '';

                sleep($input->getOption('period'));
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
     * @return Boolean Whether the asset should be dumped
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

        return $changed;
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
