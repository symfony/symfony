<?php

return \deepclone_from_array([
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
]);
