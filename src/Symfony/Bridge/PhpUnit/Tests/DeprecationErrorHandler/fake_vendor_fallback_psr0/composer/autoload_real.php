<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class ComposerLoaderFakeFallbackPsr0
{
    public function getPrefixes()
    {
        return [];
    }

    public function getPrefixesPsr4()
    {
        return [];
    }

    public function getFallbackDirs()
    {
        return [__DIR__.'/../../fake_app_fallback/'];
    }

    public function getFallbackDirsPsr4()
    {
        return [];
    }

    public function loadClass($className)
    {
        foreach ($this->getFallbackDirs() as $dir) {
            $file = $dir.strtr($className, '\\', '/').'.php';
            if (file_exists($file)) {
                require $file;

                return;
            }
        }
    }
}

class ComposerAutoloaderInitFakeFallbackPsr0
{
    private static $loader;

    public static function getLoader()
    {
        if (null === self::$loader) {
            self::$loader = new ComposerLoaderFakeFallbackPsr0();
            spl_autoload_register([self::$loader, 'loadClass']);
        }

        return self::$loader;
    }
}
