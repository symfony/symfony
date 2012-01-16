<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Metadata\Driver;

use Metadata\Driver\FileLocator as BaseFileLocator;

class FileLocator extends BaseFileLocator
{
    private $dirs = array();

    public function __construct($dirs)
    {
        parent::__construct($dirs);
        $this->dirs = $dirs;
    }

    public function findFileForClass(\ReflectionClass $class, $extension)
    {
        if ($path = parent::findFileForClass($class, $extension)) {
            return $path;
        }

        foreach ($this->dirs as $prefix => $dir) {
            if (0 !== strpos($class->getNamespaceName(), $prefix)) {
                continue;
            }

            $path = $dir . '/../validation.xml';
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }
}
