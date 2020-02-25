<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\Compiler;

use Symfony\Component\Finder\Finder;

class UnusedTagsPassUtils
{
    public static function getDefinedTags()
    {
        $tags = [
            'proxy' => true,
        ];

        // get all tags used in XML configs
        $files = Finder::create()->files()->name('*.xml')->path('Resources')->notPath('Tests')->in(\dirname(__DIR__, 5));
        foreach ($files as $file) {
            $contents = file_get_contents($file);
            if (preg_match_all('{<tag name="([^"]+)"}', $contents, $matches)) {
                foreach ($matches[1] as $match) {
                    $tags[$match] = true;
                }
            }
            if (preg_match_all('{<argument type="tagged_.+?" tag="([^"]+)"}', $contents, $matches)) {
                foreach ($matches[1] as $match) {
                    $tags[$match] = true;
                }
            }
        }

        // get all tags used in findTaggedServiceIds calls()
        $files = Finder::create()->files()->name('*.php')->path('DependencyInjection')->notPath('Tests')->in(\dirname(__DIR__, 5));
        foreach ($files as $file) {
            $contents = file_get_contents($file);
            if (preg_match_all('{findTaggedServiceIds\(\'([^\']+)\'}', $contents, $matches)) {
                foreach ($matches[1] as $match) {
                    if ('my.tag' === $match) {
                        continue;
                    }
                    $tags[$match] = true;
                }
            }
            if (preg_match_all('{findTaggedServiceIds\(\$this->([^,\)]+)}', $contents, $matches)) {
                foreach ($matches[1] as $var) {
                    if (preg_match_all('{\$'.$var.' = \'([^\']+)\'}', $contents, $matches)) {
                        foreach ($matches[1] as $match) {
                            $tags[$match] = true;
                        }
                    }
                }
            }
        }

        $tags = array_keys($tags);
        sort($tags);

        return $tags;
    }
}
