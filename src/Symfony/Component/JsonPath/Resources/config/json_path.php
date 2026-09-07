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

use Symfony\Component\JsonPath\JsonPathCrawler;
use Symfony\Component\JsonPath\JsonPathCrawlerInterface;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('json_path.crawler', JsonPathCrawler::class)
            ->args([tagged_locator('json_path.function', 'name')])
        ->alias(JsonPathCrawlerInterface::class, 'json_path.crawler')
    ;
};
