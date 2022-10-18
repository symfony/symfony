<?php

return \Symfony\Component\VarExporter\Internal\Hydrator::hydrate(
    $o = [
        clone (($p = &\Symfony\Component\VarExporter\Internal\Registry::$prototypes)['DateTime'] ?? \Symfony\Component\VarExporter\Internal\Registry::p('DateTime')),
        clone ($p['DateTimeImmutable'] ?? \Symfony\Component\VarExporter\Internal\Registry::p('DateTimeImmutable')),
        clone ($p['DateTimeZone'] ?? \Symfony\Component\VarExporter\Internal\Registry::p('DateTimeZone')),
        clone ($p['DateInterval'] ?? \Symfony\Component\VarExporter\Internal\Registry::p('DateInterval')),
        clone ($p['DatePeriod'] ?? \Symfony\Component\VarExporter\Internal\Registry::p('DatePeriod')),
        clone $p['DateTime'],
        clone $p['DateInterval'],
    ],
    null,
    [],
    [
        $o[0],
        $o[1],
        $o[2],
        $o[3],
        $o[4],
    ],
    [
        [
            'date' => '1970-01-01 00:00:00.000000',
            'timezone_type' => 1,
            'timezone' => '+00:00',
        ],
        -1 => [
            'date' => '1970-01-01 00:00:00.000000',
            'timezone_type' => 1,
            'timezone' => '+00:00',
        ],
        -2 => [
            'timezone_type' => 3,
            'timezone' => 'Europe/Paris',
        ],
        -3 => [
            'y' => 0,
            'm' => 0,
            'd' => 7,
            'h' => 0,
            'i' => 0,
            's' => 0,
            'f' => 0.0,
            'invert' => 0,
            'days' => 7,
            'from_string' => false,
        ],
        -5 => [
            'date' => '2009-10-11 00:00:00.000000',
            'timezone_type' => 3,
            'timezone' => 'Europe/Paris',
        ],
        -6 => [
            'y' => 0,
            'm' => 0,
            'd' => 7,
            'h' => 0,
            'i' => 0,
            's' => 0,
            'f' => 0.0,
            'invert' => 0,
            'days' => 7,
            'from_string' => false,
        ],
        -4 => [
            'start' => $o[5],
            'current' => null,
            'end' => null,
            'interval' => $o[6],
            'recurrences' => 5,
            'include_start_date' => true,
            'include_end_date' => false,
        ],
    ]
);
