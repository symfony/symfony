<?php

class ComposerLoaderFake
{
    public function getPrefixes()
    {
        return [];
    }

    public function getPrefixesPsr4()
    {
        return [
            'App\\Services\\' => [__DIR__.'/../../fake_app/'],
            'acme\\lib\\' => [__DIR__.'/../acme/lib/'],
            'bar\\lib\\' => [__DIR__.'/../bar/lib/'],
            'fcy\\lib\\' => [__DIR__.'/../fcy/lib/'],
        ];
    }

    public function loadClass($className)
    {
        if ($file = $this->findFile($className)) {
            require $file;
        }
    }

    public function findFile($class)
    {
        foreach ($this->getPrefixesPsr4() as $prefix => $baseDirs) {
            if (0 !== strpos($class, $prefix)) {
                continue;
            }

            foreach ($baseDirs as $baseDir) {
                $file = str_replace([$prefix, '\\'], [$baseDir, '/'], $class.'.php');
                if (file_exists($file)) {
                    return $file;
                }
            }
        }

        return false;
    }
}

class ComposerAutoloaderInitFake
{
    private static $loader;

    public static function getLoader()
    {
        if (null === self::$loader) {
            self::$loader = new ComposerLoaderFake();
            spl_autoload_register([self::$loader, 'loadClass']);
        }

        return self::$loader;
    }
}
