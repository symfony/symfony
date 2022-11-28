<?php

declare(strict_types=1);

namespace Symfony\Component\ImportMaps;

/**
 * @author Kévin Dunglas <kevin@dunglas.dev>
 */
enum Provider: string
{
    case Jspm = 'jspm';
    case JspmSystem = 'jspm.system';
    case Skypack = 'spypack';

    case JsDelivr = 'jsdelivr';
    case Unpakg = 'unpkg';
}
