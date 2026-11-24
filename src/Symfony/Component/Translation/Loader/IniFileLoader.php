<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Loader;

use Symfony\Component\Translation\Exception\InvalidResourceException;

/**
 * IniFileLoader loads translations from an ini file.
 *
 * @author stealth35
 */
class IniFileLoader extends FileLoader
{
    protected function loadResource(string $resource): array
    {
        if (false === $messages = parse_ini_file($resource, true)) {
            throw new InvalidResourceException(\sprintf('Unable to load file "%s".', $resource));
        }

        return $messages;
    }
}
