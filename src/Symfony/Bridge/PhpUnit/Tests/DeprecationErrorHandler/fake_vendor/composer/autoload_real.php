<?php

class ComposerLoaderFake
{
    public function getPrefixes()
    {
        return [];
    }

    public function getPrefixesPsr4()
    {
        return [];
    }
}

class ComposerAutoloaderInitFake
{
    public static function getLoader()
    {
        return new ComposerLoaderFake();
    }
}
