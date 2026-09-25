<?php

return (\extension_loaded('deepclone') || !\class_exists('Error') ? \deepclone_from_array([
    'classes' => 'Error',
    'objectMeta' => [
        [0, 1],
    ],
    'prepared' => 0,
    'properties' => [
        'Error' => [
            'file' => [\dirname(__DIR__).\DIRECTORY_SEPARATOR.'VarExporterTest.php'],
            'line' => [234],
            'trace' => [
                ['file' => \dirname(__DIR__).\DIRECTORY_SEPARATOR.'VarExporterTest.php', 'line' => 123],
            ],
        ],
    ],
    'states' => [1 => 0],
], null, true) : \unserialize('O:5:"Error":7:{s:10:"'."\0".'*'."\0".'message";s:0:"";s:13:"'."\0".'Error'."\0".'string";s:0:"";s:7:"'."\0".'*'."\0".'code";i:0;s:7:"'."\0".'*'."\0".'file";s:128:"/home/nicol/Code/symfony-worktrees/var-exporter-unserialize-fallback/src/Symfony/Component/VarExporter/Tests/VarExporterTest.php";s:7:"'."\0".'*'."\0".'line";i:234;s:12:"'."\0".'Error'."\0".'trace";a:2:{s:4:"file";s:128:"/home/nicol/Code/symfony-worktrees/var-exporter-unserialize-fallback/src/Symfony/Component/VarExporter/Tests/VarExporterTest.php";s:4:"line";i:123;}s:15:"'."\0".'Error'."\0".'previous";N;}'));
