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

use Symfony\Component\Intl\DateIntervalFormatter\DateIntervalFormatter;
use Symfony\Component\Intl\DateIntervalFormatter\DateIntervalFormatterInterface;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('intl.date_interval_formatter', DateIntervalFormatter::class)
            ->alias(DateIntervalFormatterInterface::class, 'intl.date_interval_formatter')
    ;
};
