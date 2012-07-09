<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Bundle;

use Symfony\Component\Console\Application;
use Symfony\Component\Finder\Finder;

/**
 * An extension of Bundle that adds support for Console commands.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class CommandBundleService
{
    protected $commandPath = '/Command';
    protected $filePattern = '*Command.php';
    protected $commandNamespace = '\\Command';
    protected $requiredCommandBaseClass = 'Symfony\\Component\\Console\\Command\\Command';

    /**
     * Set command path
     *
     * @param string $commandPath Command path
     *
     * @return CommandBundleService
     */
    public function setCommandPath($commandPath)
    {
        $this->commandPath = $commandPath;

        return $this;
    }

    /**
     * Set file pattern for tests
     *
     * @param string $filePattern File pattern
     *
     * @return CommandBundleService
     */
    public function setFilePattern($filePattern)
    {
        $this->filePattern = $filePattern;

        return $this;
    }

    /**
     * Set command namespace
     *
     * Defaults to '\\Command'
     *
     * @param string $commandNamespace Command namespace
     *
     * @return CommandBundleService
     */
    public function setCommandNamespace($commandNamespace)
    {
        $this->commandNamespace = $commandNamespace;

        return $this;
    }

    /**
     * Set required base class
     *
     * @param string $requiredCommandBaseClass Required command base class
     *
     * @return CommandBundleService
     */
    public function setRequiredCommandBaseClass($requiredCommandBaseClass)
    {
        $this->requiredCommandBaseClass = $requiredCommandBaseClass;

        return $this;
    }

    /**
     * Finds and registers Commands.
     *
     * @param Bundle      $bundle      A Bundle instance
     * @param Application $application An Application instance
     */
    public function registerCommands(Bundle $bundle, Application $application)
    {
        if (!$dir = realpath($bundle->getPath().$this->commandPath)) {
            return;
        }

        $finder = new Finder();
        $finder->files()->name($this->filePattern)->in($dir);

        $prefix = $bundle->getNamespace().$this->commandNamespace;
        foreach ($finder as $file) {
            $ns = $prefix;
            if ($relativePath = $file->getRelativePath()) {
                $ns .= '\\'.strtr($relativePath, '/', '\\');
            }
            $r = new \ReflectionClass($ns.'\\'.$file->getBasename('.php'));
            if ($r->isSubclassOf($this->requiredCommandBaseClass) && !$r->isAbstract()) {
                $application->add($r->newInstance());
            }
        }
    }
}
