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
Builder::saveRules(Builder::buildGitHubRules($emojisCodePoints));
Builder::saveRules(Builder::buildSlackRules($emojisCodePoints));

final class Builder
{
    private const TARGET_DIR = __DIR__.'/../data/transliterator/emoji/';

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

        ksort($mapsByLocale);

        foreach ($mapsByLocale as $locale => $maps) {
            $parentLocale = $locale;

            while (false !== $i = strrpos($parentLocale, '_')) {
                $parentLocale = substr($parentLocale, 0, $i);
                $maps += $mapsByLocale[$parentLocale] ?? [];
            }

            yield strtolower("emoji-$locale") => self::createRules($maps);
        }
    }

    public static function buildGitHubRules(array $emojisCodePoints): iterable
    {
        $emojis = json_decode(file_get_contents(__DIR__.'/vendor/github-emojis.json'), true);

        $ignored = [];
        $maps = [];

        foreach ($emojis as $shortCode => $url) {
            $emojiCodePoints = str_replace('-', ' ', strtolower(basename(parse_url($url, \PHP_URL_PATH), '.png')));
            if (!array_key_exists($emojiCodePoints, $emojisCodePoints)) {
                $ignored[] = [
                    'emojiCodePoints' => $emojiCodePoints,
                    'shortCode' => $shortCode,
                ];
                continue;
            }
            $emoji = $emojisCodePoints[$emojiCodePoints];
            self::testEmoji($emoji, 'github');
            $codePointsCount = mb_strlen($emoji);
            $maps[$codePointsCount][$emoji] = ":$shortCode:";
        }

        $maps = self::createRules($maps);

        return ['emoji-github' => $maps, 'github-emoji' => array_flip($maps)];
    }

    public static function buildSlackRules(array $emojisCodePoints): iterable
    {
        $emojis = json_decode(file_get_contents(__DIR__.'/vendor/slack-emojis.json'), true);

        $ignored = [];
        $emojiSlackMaps = [];
        $slackEmojiMaps = [];

        foreach ($emojis as $data) {
            $emojiCodePoints = str_replace('-', ' ', strtolower($data['unified']));
            $shortCode = $data['short_name'];
            $shortCodes = $data['short_names'];
            $shortCodes = array_map(fn ($v) => ":$v:", $shortCodes);

            if (!array_key_exists($emojiCodePoints, $emojisCodePoints)) {
                $ignored[] = [
                    'emojiCodePoints' => $emojiCodePoints,
                    'shortCode' => $shortCode,
                ];
                continue;
            }
            $emoji = $emojisCodePoints[$emojiCodePoints];
            self::testEmoji($emoji, 'slack');
            $codePointsCount = mb_strlen($emoji);
            $emojiSlackMaps[$codePointsCount][$emoji] = ":$shortCode:";
            foreach ($shortCodes as $short_name) {
                $slackEmojiMaps[$codePointsCount][$short_name] = $emoji;
            }
        }

        return ['emoji-slack' => self::createRules($emojiSlackMaps), 'slack-emoji' => self::createRules($slackEmojiMaps)];
    }

    public static function buildStripRules(array $emojisCodePoints): iterable
    {
        $maps = [];
        foreach ($emojisCodePoints as $emoji) {
            self::testEmoji($emoji, 'strip');
            $codePointsCount = mb_strlen($emoji);
            $maps[$codePointsCount][$emoji] = '';
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
        $firstChars = [];
        foreach ($rulesByLocale as $filename => $rules) {
            file_put_contents(self::TARGET_DIR."/$filename.php", "<?php\n\nreturn ".VarExporter::export($rules).";\n");

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
        $file = dirname(__DIR__, 2).'/Transliterator/EmojiTransliterator.php';
        file_put_contents($file, preg_replace('/QUICK_CHECK = .*;/m', "QUICK_CHECK = {$quickCheck};", file_get_contents($file)));
    }

    private static function testEmoji(string $emoji, string $locale): void
    {
        if (!Transliterator::createFromRules("\\$emoji > test ;")) {
            throw new \RuntimeException(sprintf('Could not create transliterator for "%s" in "%s" locale. Error: "%s".', $emoji, $locale, intl_get_error_message()));
        }
    }

    private static function createRules(array $maps): array
    {
        // We must sort the maps by the number of code points, because the order really matters:
        // 🫶🏼 must be before 🫶
        krsort($maps);
        $maps = array_merge(...$maps);

        return $maps;
    }
}
