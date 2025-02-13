<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\StaticSiteGeneration;

/**
 * Dump a static page content into a storage.
 *
 * @author Thomas Bibaut <bibaut.t@gmail.com>
 */
interface StaticPageDumperInterface
{
    public function dump(string $uri, string $content, ?string $format): void;
}
