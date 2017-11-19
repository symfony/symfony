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
        $blacklist = array();

        foreach (get_declared_classes() as $class) {
            if ('C' === $class[0] && 0 === strpos($class, 'ComposerAutoloaderInit')) {
                $r = new \ReflectionClass($class);
                $v = dirname(dirname($r->getFileName()));
                if (file_exists($v.'/composer/installed.json')) {
                    $blacklist[] = $v;
                }
            }
        }

        return $blacklist;
    }

    public function isBlacklisted($file)
    {
        return true;
    }
}

if (class_exists('PHPUnit\Util\Test')) {
    class_alias('Symfony\Bridge\PhpUnit\Blacklist', 'PHPUnit\Util\Blacklist');
}
if (class_exists('PHPUnit_Util_Test')) {
    class_alias('Symfony\Bridge\PhpUnit\Blacklist', 'PHPUnit_Util_Blacklist');
}
