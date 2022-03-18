<?php

return \Symfony\Component\VarExporter\Internal\Hydrator::hydrate(
    $o = \Symfony\Component\VarExporter\Internal\Registry::unserialize([], [
        'O:8:"DateTime":3:{s:4:"date";s:26:"1970-01-01 00:00:00.000000";s:13:"timezone_type";i:1;s:8:"timezone";s:6:"+00:00";}',
        'O:17:"DateTimeImmutable":3:{s:4:"date";s:26:"1970-01-01 00:00:00.000000";s:13:"timezone_type";i:1;s:8:"timezone";s:6:"+00:00";}',
        'O:12:"DateTimeZone":2:{s:13:"timezone_type";i:3;s:8:"timezone";s:12:"Europe/Paris";}',
        'O:12:"DateInterval":16:{s:1:"y";i:0;s:1:"m";i:0;s:1:"d";i:7;s:1:"h";i:0;s:1:"i";i:0;s:1:"s";i:0;s:1:"f";d:0;s:7:"weekday";i:0;s:16:"weekday_behavior";i:0;s:17:"first_last_day_of";i:0;s:6:"invert";i:0;s:4:"days";i:7;s:12:"special_type";i:0;s:14:"special_amount";i:0;s:21:"have_weekday_relative";i:0;s:21:"have_special_relative";i:0;}',
        'O:10:"DatePeriod":6:{s:5:"start";O:8:"DateTime":3:{s:4:"date";s:26:"2009-10-11 00:00:00.000000";s:13:"timezone_type";i:3;s:8:"timezone";s:12:"Europe/Paris";}s:7:"current";N;s:3:"end";N;s:8:"interval";O:12:"DateInterval":16:{s:1:"y";i:0;s:1:"m";i:0;s:1:"d";i:7;s:1:"h";i:0;s:1:"i";i:0;s:1:"s";i:0;s:1:"f";d:0;s:7:"weekday";i:0;s:16:"weekday_behavior";i:0;s:17:"first_last_day_of";i:0;s:6:"invert";i:0;s:4:"days";i:7;s:12:"special_type";i:0;s:14:"special_amount";i:0;s:21:"have_weekday_relative";i:0;s:21:"have_special_relative";i:0;}s:11:"recurrences";i:5;s:18:"include_start_date";b:1;}',
    ]),
    null,
    [],
    [
        $o[0],
        $o[1],
        $o[2],
        $o[3],
        $o[4],
    ],
    []
);
