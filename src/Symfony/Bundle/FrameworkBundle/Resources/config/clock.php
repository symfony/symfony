<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Psr\Clock\ClockInterface as PsrClockInterface;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\ClockInterface;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('clock', Clock::class)
        ->alias(ClockInterface::class, 'clock')
        ->alias(PsrClockInterface::class, 'clock');
};
