<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ImportMaps;

/**
 * @author Kévin Dunglas <kevin@dunglas.dev>
 */
enum Provider: string
{
    case Jspm = 'jspm';
    case JspmSystem = 'jspm.system';
    case Skypack = 'skypack';
    case JsDelivr = 'jsdelivr';
    case Unpkg = 'unpkg';
}
