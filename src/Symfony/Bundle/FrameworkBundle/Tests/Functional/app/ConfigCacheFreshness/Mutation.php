<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional\app\ConfigCacheFreshness;

class Mutation
{
    public static string $directory;
    public static string $mode;

    /**
     * Saves changed input once, using the committed replacement for compiled ICU bundles.
     */
    public static function change(string $path): void
    {
        $marker = self::$directory.'/changed';
        if (is_file($marker)) {
            return;
        }
        if (is_dir($path)) {
            $path .= '/en.res';
        } elseif (is_file($path.'.dat')) {
            $path .= '.dat';
        }
        $before = filemtime($path);
        $contents = match (self::$mode) {
            'translation-dat' => file_get_contents(__DIR__.'/translations/resourcebundle/after/dat/resources.dat'),
            'translation-res' => file_get_contents(__DIR__.'/translations/resourcebundle/after/res/en.res'),
            default => str_replace('v1', 'v2', file_get_contents($path)),
        };
        file_put_contents($path, $contents);
        if (\in_array(self::$mode, ['translation-yml', 'translation-xliff', 'translation-qt', 'translation-dat', 'translation-res'], true)) {
            // XML inputs start with an old mtime; advance it without depending on the clocks used by PHP and the filesystem agreeing.
            $after = \in_array(self::$mode, ['translation-xliff', 'translation-qt'], true) ? $before + 1 : time() - 1;
            touch($path, $after);
            file_put_contents(self::$directory.'/translation-mutation.json', json_encode(['before' => $before, 'after' => $after], \JSON_THROW_ON_ERROR));
        }
        file_put_contents($marker, $path);
        clearstatcache();
    }
}
