<?php

class ComposerLoaderFakeBis
{
    public function getPrefixes()
    {
        return [];
    }

    public function getPrefixesPsr4()
    {
        return [
            'foo\\lib\\' => [__DIR__.'/../foo/lib/'],
        ];
    }

    public function loadClass($className)
    {
        foreach ($this->getPrefixesPsr4() as $prefix => $baseDirs) {
            if (0 !== strpos($className, $prefix)) {
                continue;
            }

            foreach ($baseDirs as $baseDir) {
                $file = str_replace([$prefix, '\\'], [$baseDir, '/'], $className.'.php');
                if (file_exists($file)) {
                    require $file;
                }
            }
        }
    }
}

class ComposerAutoloaderInitFakeBis
{
    private static $loader;

    public static function getLoader()
    {
        if (null === self::$loader) {
            self::$loader = new ComposerLoaderFakeBis();
            spl_autoload_register([self::$loader, 'loadClass']);
        }

        return self::$loader;
    }
}
