<?php

return (\extension_loaded('deepclone') || !\class_exists('DateTime') || !\class_exists('DateTimeImmutable') || !\class_exists('DateTimeZone') || !\class_exists('DateInterval') || !\class_exists('DatePeriod') ? \deepclone_from_array([
    'classes' => ['DateTime', 'DateTimeImmutable', 'DateTimeZone', 'DateInterval', 'DatePeriod'],
    'objectMeta' => [
        [0, -1],
        [1, -2],
        [2, -3],
        [3, -4],
        [4, -7],
        [1, -5],
        [3, -6],
    ],
    'prepared' => [0, 1, 2, 3, 4],
    'mask' => [true, true, true, true, true],
    'states' => [
        1 => [
            0,
            ['date' => '1970-01-01 00:00:00.000000', 'timezone_type' => 1, 'timezone' => '+00:00'],
        ],
        [
            1,
            ['date' => '1970-01-01 00:00:00.000000', 'timezone_type' => 1, 'timezone' => '+00:00'],
        ],
        [
            2,
            ['timezone_type' => 3, 'timezone' => 'Europe/Paris'],
        ],
        [
            3,
            ['y' => 0, 'm' => 0, 'd' => 7, 'h' => 0, 'i' => 0, 's' => 0, 'f' => 0.0, 'invert' => 0, 'days' => 7, 'from_string' => false],
        ],
        [
            5,
            ['date' => '2009-10-11 00:00:00.000000', 'timezone_type' => 3, 'timezone' => 'Europe/Paris'],
        ],
        [
            6,
            ['y' => 0, 'm' => 0, 'd' => 7, 'h' => 0, 'i' => 0, 's' => 0, 'f' => 0.0, 'invert' => 0, 'days' => 7, 'from_string' => false],
        ],
        [
            4,
            ['start' => 5, 'current' => null, 'end' => null, 'interval' => 6, 'recurrences' => 5, 'include_start_date' => true, 'include_end_date' => false],
            ['start' => true, 'interval' => true],
        ],
    ],
], null, true) : \unserialize('a:5:{i:0;O:8:"DateTime":3:{s:4:"date";s:26:"1970-01-01 00:00:00.000000";s:13:"timezone_type";i:1;s:8:"timezone";s:6:"+00:00";}i:1;O:17:"DateTimeImmutable":3:{s:4:"date";s:26:"1970-01-01 00:00:00.000000";s:13:"timezone_type";i:1;s:8:"timezone";s:6:"+00:00";}i:2;O:12:"DateTimeZone":2:{s:13:"timezone_type";i:3;s:8:"timezone";s:12:"Europe/Paris";}i:3;O:12:"DateInterval":10:{s:1:"y";i:0;s:1:"m";i:0;s:1:"d";i:7;s:1:"h";i:0;s:1:"i";i:0;s:1:"s";i:0;s:1:"f";d:0;s:6:"invert";i:0;s:4:"days";i:7;s:11:"from_string";b:0;}i:4;O:10:"DatePeriod":7:{s:5:"start";O:17:"DateTimeImmutable":3:{s:4:"date";s:26:"2009-10-11 00:00:00.000000";s:13:"timezone_type";i:3;s:8:"timezone";s:12:"Europe/Paris";}s:7:"current";N;s:3:"end";N;s:8:"interval";O:12:"DateInterval":10:{s:1:"y";i:0;s:1:"m";i:0;s:1:"d";i:7;s:1:"h";i:0;s:1:"i";i:0;s:1:"s";i:0;s:1:"f";d:0;s:6:"invert";i:0;s:4:"days";i:7;s:11:"from_string";b:0;}s:11:"recurrences";i:5;s:18:"include_start_date";b:1;s:16:"include_end_date";b:0;}}'));
