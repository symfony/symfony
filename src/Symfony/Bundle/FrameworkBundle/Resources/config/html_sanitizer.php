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

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('html_sanitizer.config', HtmlSanitizerConfig::class)
            ->call('allowSafeElements')

        ->set('html_sanitizer', HtmlSanitizer::class)
            ->args([service('html_sanitizer.config')])
    ;
};
