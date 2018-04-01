<?php

namespace Symphony\Component\Routing\Tests\Loader;

use Symphony\Component\Config\FileLocatorInterface;

class FileLocatorStub implements FileLocatorInterface
{
    public function locate($name, $currentPath = null, $first = true)
    {
        if (0 === strpos($name, 'http')) {
            return $name;
        }

        return rtrim($currentPath, '/').'/'.$name;
    }
}
