<?php

return \Symfony\Component\VarExporter\Internal\Hydrator::hydrate(
    $o = \Symfony\Component\VarExporter\Internal\Registry::unserialize([
        clone (($p = &\Symfony\Component\VarExporter\Internal\Registry::$prototypes)['DateTime'] ?? \Symfony\Component\VarExporter\Internal\Registry::p('DateTime')),
        clone ($p['DateTimeImmutable'] ?? \Symfony\Component\VarExporter\Internal\Registry::p('DateTimeImmutable')),
        clone ($p['DateTimeZone'] ?? \Symfony\Component\VarExporter\Internal\Registry::p('DateTimeZone')),
        clone ($p['DateInterval'] ?? \Symfony\Component\VarExporter\Internal\Registry::p('DateInterval')),
    ], [
        4 => 'O:10:"DatePeriod":6:{s:5:"start";O:8:"DateTime":3:{s:4:"date";s:26:"2009-10-11 00:00:00.000000";s:13:"timezone_type";i:3;s:8:"timezone";s:12:"Europe/Paris";}s:7:"current";N;s:3:"end";N;s:8:"interval";O:12:"DateInterval":16:{s:1:"y";i:0;s:1:"m";i:0;s:1:"d";i:7;s:1:"h";i:0;s:1:"i";i:0;s:1:"s";i:0;s:1:"f";d:0;s:7:"weekday";i:0;s:16:"weekday_behavior";i:0;s:17:"first_last_day_of";i:0;s:6:"invert";i:0;s:4:"days";i:7;s:12:"special_type";i:0;s:14:"special_amount";i:0;s:21:"have_weekday_relative";i:0;s:21:"have_special_relative";i:0;}s:11:"recurrences";i:5;s:18:"include_start_date";b:1;}',
    ]),
    null,
    [
        'stdClass' => [
            'date' => [
                '1970-01-01 00:00:00.000000',
                '1970-01-01 00:00:00.000000',
            ],
            'timezone_type' => [
                1,
                1,
                3,
            ],
            'timezone' => [
                '+00:00',
                '+00:00',
                'Europe/Paris',
            ],
            'y' => [
                3 => 0,
            ],
            'm' => [
                3 => 0,
            ],
            'd' => [
                3 => 7,
            ],
            'h' => [
                3 => 0,
            ],
            'i' => [
                3 => 0,
            ],
            's' => [
                3 => 0,
            ],
            'f' => [
                3 => 0.0,
            ],
            'weekday' => [
                3 => 0,
            ],
            'weekday_behavior' => [
                3 => 0,
            ],
            'first_last_day_of' => [
                3 => 0,
            ],
            'invert' => [
                3 => 0,
            ],
            'days' => [
                3 => 7,
            ],
            'special_type' => [
                3 => 0,
            ],
            'special_amount' => [
                3 => 0,
            ],
            'have_weekday_relative' => [
                3 => 0,
            ],
            'have_special_relative' => [
                3 => 0,
            ],
        ],
    ],
    [
        $o[0],
        $o[1],
        $o[2],
        $o[3],
        $o[4],
    ],
    [
        1 => 0,
        1,
        2,
        3,
    ]
);
