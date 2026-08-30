<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

return Symfony\Component\VarExporter\Internal\Hydrator::hydrate(
    $o = [
        clone (Symfony\Component\VarExporter\Internal\Registry::$prototypes['stdClass'] ?? Symfony\Component\VarExporter\Internal\Registry::p('stdClass')),
    ],
    null,
    [
        'stdClass' => [
            'foo' => [
                'bar',
            ],
        ],
    ],
    $o[0],
    []
);
