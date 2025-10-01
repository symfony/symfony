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
use Symfony\Component\VarExporter\VarExporter;

Builder::cleanTarget();
$emojisCodePoints = Builder::getEmojisCodePoints();
Builder::saveRules(Builder::buildRules($emojisCodePoints));
Builder::saveRules(Builder::buildStripRules($emojisCodePoints));

$emojiMaps = ['slack', 'github', 'gitlab'];

foreach ($emojiMaps as $map) {
    $maps = Builder::{"build{$map}Maps"}($emojisCodePoints);
    Builder::saveRules(array_combine(["emoji-$map", "$map-emoji"], Builder::createRules($maps, true)));
}

Builder::saveRules(Builder::buildTextRules($emojisCodePoints, $emojiMaps));

final class Builder
{
    private const TARGET_DIR = __DIR__.'/../data/';

    public static function getEmojisCodePoints(): array
    {
        $lines = file(__DIR__.'/vendor/emoji-test.txt');

        $emojisCodePoints = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (!$line || str_starts_with($line, '#')) {
                continue;
            }

            // 263A FE0F    ; fully-qualified     # ☺️ E0.6 smiling face
            preg_match('{^(?<codePoints>[\w ]+) +; [\w-]+ +# (?<emoji>.+) E\d+\.\d+ ?(?<name>.+)$}Uu', $line, $matches);
            if (!$matches) {
                throw new DomainException("Could not parse line: \"$line\".");
            }

            $codePoints = str_replace(' ', '-', trim($matches['codePoints']));
            $emojisCodePoints[$codePoints] = $matches['emoji'];
            // We also add a version without the "Zero Width Joiner"
            $codePoints = str_replace('-200D-', '-', $codePoints);
            $emojisCodePoints[$codePoints] = $matches['emoji'];
        }

        return $emojisCodePoints;
    }

    public static function buildRules(array $emojisCodePoints): Generator
    {
        $filesystem = new Filesystem();
        $files = (new Finder())
            ->files()
            ->in([
                __DIR__.'/vendor/unicode-org/cldr/common/annotationsDerived',
                __DIR__.'/vendor/unicode-org/cldr/common/annotations',
            ])
            ->name('*.xml')
        ;

        $mapsByLocale = [];

        foreach ($files as $file) {
            $locale = $file->getBasename('.xml');

            $mapsByLocale[$locale] ??= [];

            $document = new DOMDocument();
            $document->loadXML($filesystem->readFile($file));
            $xpath = new DOMXPath($document);
            $results = $xpath->query('.//annotation[@type="tts"]');

            foreach ($results as $result) {
                $emoji = $result->getAttribute('cp');
                $name = $result->textContent;
                // Ignoring the hierarchical metadata instructions
                // (real value will be filled by the parent locale)
                if (str_contains($name, '↑↑')) {
                    continue;
                }
                $parts = preg_split('//u', $emoji, -1, \PREG_SPLIT_NO_EMPTY);
                $emojiCodePoints = strtoupper(implode('-', array_map('dechex', array_map('mb_ord', $parts))));
                if (!array_key_exists($emojiCodePoints, $emojisCodePoints)) {
                    continue;
                }
                $codePointsCount = mb_strlen($emoji);
                $mapsByLocale[$locale][$codePointsCount][$emoji] = $name;
            }
        }

        ksort($mapsByLocale);

        foreach ($mapsByLocale as $locale => $localeMaps) {
            $parentLocale = $locale;

            while (false !== $i = strrpos($parentLocale, '_')) {
                $parentLocale = substr($parentLocale, 0, $i);
                $parentMaps = $mapsByLocale[$parentLocale] ?? [];
                foreach ($parentMaps as $codePointsCount => $parentMap) {
                    // Ensuring the result map contains all the emojis from the parent map
                    // if not already defined by the current locale
                    $localeMaps[$codePointsCount] = [...$parentMap, ...$localeMaps[$codePointsCount] ?? []];
                }
            }

            // Skip locales without any emoji
            if ($localeRules = self::createRules($localeMaps)) {
                yield strtolower("emoji-$locale") => $localeRules;
            }
        }
    }

    public static function buildGitHubMaps(array $emojisCodePoints): array
    {
        $emojis = json_decode((new Filesystem())->readFile(__DIR__.'/vendor/github-emojis.json'), true, flags: JSON_THROW_ON_ERROR);
        $maps = [];

        foreach ($emojis as $shortCode => $url) {
            $emojiCodePoints = strtoupper(basename(parse_url($url, \PHP_URL_PATH), '.png'));

            if (!array_key_exists($emojiCodePoints, $emojisCodePoints)) {
                continue;
            }
            $emoji = $emojisCodePoints[$emojiCodePoints];
            $emojiPriority = mb_strlen($emoji) << 1;
            $maps[$emojiPriority + 1][":$shortCode:"] = $emoji;
        }

        return $maps;
    }

    public static function buildGitlabMaps(array $emojisCodePoints): array
    {
        $emojis = json_decode((new Filesystem())->readFile(__DIR__.'/vendor/gitlab-emojis.json'), true, flags: JSON_THROW_ON_ERROR);
        $maps = [];

        foreach ($emojis as $shortName => $emojiItem) {
            $emoji = $emojiItem['moji'];
            $emojiPriority = mb_strlen($emoji) << 1;
            $maps[$emojiPriority + 1][":$shortName:"] = $emoji;
        }

        return $maps;
    }

    public static function buildSlackMaps(array $emojisCodePoints): array
    {
        $emojis = json_decode((new Filesystem())->readFile(__DIR__.'/vendor/slack-emojis.json'), true, flags: JSON_THROW_ON_ERROR);
        $maps = [];

        foreach ($emojis as $data) {
            $emoji = $emojisCodePoints[$data['unified']];
            $emojiPriority = mb_strlen($emoji) << 1;
            $maps[$emojiPriority + 1][":{$data['short_name']}:"] = $emoji;

            foreach ($data['short_names'] as $shortName) {
                $maps[$emojiPriority][":$shortName:"] = $emoji;
            }
        }

        return $maps;
    }

    public static function buildTextRules(array $emojiCodePoints, array $locales): iterable
    {
        $maps = [];

        foreach ($locales as $locale) {
            foreach (self::{"build{$locale}Maps"}($emojiCodePoints) as $emojiPriority => $map) {
                foreach ($map as $text => $emoji) {
                    $maps[$emojiPriority][str_replace('_', '-', $text)] ??= $emoji;
                }
            }
        }

        [$map, $reverse] = self::createRules($maps, true);

        return ['emoji-text' => $map, 'text-emoji' => $reverse];
    }

    public static function buildStripRules(array $emojisCodePoints): iterable
    {
        $maps = [];
        foreach ($emojisCodePoints as $emoji) {
            $maps[mb_strlen($emoji)][$emoji] = '';
        }

        return ['emoji-strip' => self::createRules($maps)];
    }

    public static function cleanTarget(): void
    {
        $fs = new Filesystem();
        $fs->remove(self::TARGET_DIR);
        $fs->mkdir(self::TARGET_DIR);
    }

    public static function saveRules(iterable $rulesByLocale): void
    {
        $fs = new Filesystem();
        $firstChars = [];
        foreach ($rulesByLocale as $filename => $rules) {
            $fs->dumpFile(self::TARGET_DIR."/$filename.php", "<?php\n\nreturn ".VarExporter::export($rules).";\n");

            foreach ($rules as $k => $v) {
                if (!str_starts_with($filename, 'emoji-')) {
                    continue;
                }
                for ($i = 0; ord($k[$i]) < 128 || "\xC2" === $k[$i]; ++$i) {
                }
                for ($j = $i; isset($k[$j]) && !isset($firstChars[$k[$j]]); ++$j) {
                }
                $c = $k[$j] ?? $k[$i];
                $firstChars[$c] = $c;
            }
        }

        sort($firstChars);

        $quickCheck = '"'.str_replace('%', '\\x', rawurlencode(implode('', $firstChars))).'"';
        $file = dirname(__DIR__, 2).'/EmojiTransliterator.php';
        $fs->dumpFile($file, preg_replace('/QUICK_CHECK = .*;/m', "QUICK_CHECK = {$quickCheck};", $fs->readFile($file)));
    }

    public static function createRules(array $maps, bool $reverse = false): array
    {
        // We must sort the maps by the number of code points, because the order really matters:
        // 🫶🏼 must be before 🫶
        krsort($maps);

        if (!$reverse) {
            return array_merge(...$maps);
        }

        $emojiText = $textEmoji = [];

        foreach ($maps as $map) {
            uksort($map, static fn ($a, $b) => strnatcmp(substr($a, 1, -1), substr($b, 1, -1)));
            $textEmoji = array_merge($map, $textEmoji);

            $map = array_flip($map);
            $emojiText += $map;
        }

        return [$emojiText, $textEmoji];
    }
}
