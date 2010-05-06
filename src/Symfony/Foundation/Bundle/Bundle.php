<?php

namespace Symfony\Foundation\Bundle;

use Symfony\Components\DependencyInjection\ContainerInterface;
use Symfony\Components\Console\Application;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Bundle.
 *
 * @package    Symfony
 * @subpackage Foundation
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
abstract class Bundle implements BundleInterface
{
    public function buildContainer(ContainerInterface $container)
    {
    }

    public function boot(ContainerInterface $container)
    {
    }

    public function shutdown(ContainerInterface $container)
    {
    }

    public function registerCommands(Application $application)
    {
        foreach ($application->getKernel()->getBundleDirs() as $dir)
        {
            $bundleBase = dirname(str_replace('\\', '/', get_class($this)));
            $commandDir = $dir.'/'.basename($bundleBase).'/Command';
            if (!is_dir($commandDir))
            {
                continue;
            }

            // look for commands
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($commandDir), \RecursiveIteratorIterator::LEAVES_ONLY) as $file)
            {
                if ($file->isDir() || substr($file, -4) !== '.php')
                {
                    continue;
                }

                $class = str_replace('/', '\\', $bundleBase).'\\Command\\'.str_replace(realpath($commandDir).'/', '', basename(realpath($file), '.php'));

                $r = new \ReflectionClass($class);

                if ($r->isSubclassOf('Symfony\\Components\\Console\\Command\\Command') && !$r->isAbstract())
                {
                    $application->addCommand(new $class());
                }
            }
        }
    }
}
