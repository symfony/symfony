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

use Symfony\Component\Mime\MimeTypes;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('mime_types', MimeTypes::class)
            ->call('setDefault', [service('mime_types')])
    ;
};
