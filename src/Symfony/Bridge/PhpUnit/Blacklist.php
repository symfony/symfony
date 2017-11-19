<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PhpUnit;

/**
 * Utility class replacing PHPUnit's implementation of the same class.
 *
 * All files are blacklisted so that process-isolated tests don't start with broken
 * "require_once" statements. Composer is the only supported way to load code there.
 */
class Blacklist
{
    public static $blacklistedClassNames = array();

    public function getBlacklistedDirectories()
    {
        $root = dirname(__DIR__);
        while ($root !== $parent = dirname($root)) {
            $root = $parent;
        }

        return array($root);
    }

    public function isBlacklisted($file)
    {
        return true;
    }
}

class_alias('Symfony\Bridge\PhpUnit\Blacklist', 'PHPUnit\Util\Blacklist');
class_alias('Symfony\Bridge\PhpUnit\Blacklist', 'PHPUnit_Util_Blacklist');
