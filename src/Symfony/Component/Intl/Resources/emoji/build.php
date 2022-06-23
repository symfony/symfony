#!/usr/bin/env php
<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require __DIR__.'/vendor/autoload.php';

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

Builder::cleanTarget();
$emojisCodePoints = Builder::getEmojisCodePoints();
Builder::saveRules(Builder::buildRules($emojisCodePoints));

final class Builder
{
    private const TARGET_DIR = __DIR__.'/../data/transliterator/emoji/';

    public static function getEmojisCodePoints(): array
    {
        $lines = file(__DIR__.'/vendor/unicode-org/cldr/tools/cldr-code/src/main/resources/org/unicode/cldr/util/data/emoji/emoji-test.txt');

        $emojisCodePoints = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (!$line || str_starts_with($line, '#')) {
                continue;
            }

            // 263A FE0F    ; fully-qualified     # ☺️ E0.6 smiling face
            preg_match('{^(?<codePoints>[\w ]+) +; [\w-]+ +# (?<emoji>.+) E\d+\.\d+ ?(?<name>.+)$}Uu', $line, $matches);
            if (!$matches) {
                throw new \DomainException("Could not parse line: \"$line\".");
            }

            $codePoints = strtolower(trim($matches['codePoints']));
            $emojisCodePoints[$codePoints] = $matches['emoji'];
            // We also add a version without the "Zero Width Joiner"
            $codePoints = str_replace('200d ', '', $codePoints);
            $emojisCodePoints[$codePoints] = $matches['emoji'];
        }

        return $emojisCodePoints;
    }

    public static function buildRules(array $emojisCodePoints): Generator
    {
        $files = (new Finder())
            ->files()
            ->in([
                __DIR__.'/vendor/unicode-org/cldr/common/annotationsDerived',
                __DIR__.'/vendor/unicode-org/cldr/common/annotations',
            ])
            ->name('*.xml')
        ;

        $ignored = [];
        $mapsByLocale = [];

        foreach ($files as $file) {
            $locale = $file->getBasename('.xml');

            $document = new DOMDocument();
            $document->loadXML(file_get_contents($file));
            $xpath = new DOMXPath($document);
            $results = $xpath->query('.//annotation[@type="tts"]');

            foreach ($results as $result) {
                $emoji = $result->getAttribute('cp');
                $name = $result->textContent;
                $parts = preg_split('//u', $emoji, -1, \PREG_SPLIT_NO_EMPTY);
                $emojiCodePoints = implode(' ', array_map('dechex', array_map('mb_ord', $parts)));
                if (!array_key_exists($emojiCodePoints, $emojisCodePoints)) {
                    $ignored[] = [
                        'locale' => $locale,
                        'emoji' => $emoji,
                        'name' => $name,
                    ];
                    continue;
                }
                self::testEmoji($emoji, $locale);
                $codePointsCount = mb_strlen($emoji);
                $mapsByLocale[$locale][$codePointsCount][$emoji] = $name;
            }
        }

        foreach ($mapsByLocale as $locale => $maps) {
            yield $locale => self::createRules($maps);
        }
    }

    public static function cleanTarget(): void
    {
        $fs = new Filesystem();
        $fs->remove(self::TARGET_DIR);
        $fs->mkdir(self::TARGET_DIR);
    }

    public static function saveRules(iterable $rulesByLocale): void
    {
        foreach ($rulesByLocale as $locale => $rules) {
            file_put_contents(self::TARGET_DIR."/$locale.txt", $rules);
        }
    }

    private static function testEmoji(string $emoji, string $locale): void
    {
        if (!Transliterator::createFromRules("\\$emoji > test ;")) {
            throw new \RuntimeException(sprintf('Could not create transliterator for "%s" in "%s" locale. Error: "%s".', $emoji, $locale, intl_get_error_message()));
        }
    }

    private static function createRules(array $maps): string
    {
        // We must sort the maps by the number of code points, because the order really matters:
        // 🫶🏼 must be before 🫶
        krsort($maps);
        $maps = array_merge(...$maps);

        $rules = '';
        foreach ($maps as $emoji => $name) {
            $name = preg_replace('{([^[:alnum:]])}u', '\\\\$1', $name);
            $rules .= "\\$emoji > $name ;\n";
        }

        return $rules;
    }
}
