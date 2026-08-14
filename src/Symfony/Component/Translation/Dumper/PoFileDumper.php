<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Dumper;

use Symfony\Component\Translation\MessageCatalogue;

/**
 * PoFileDumper generates a gettext formatted string representation of a message catalogue.
 *
 * @author Stealth35
 */
class PoFileDumper extends FileDumper
{
    public function formatCatalogue(MessageCatalogue $messages, string $domain, array $options = []): string
    {
        $output = 'msgid ""'."\n";
        $output .= 'msgstr ""'."\n";
        $output .= '"Content-Type: text/plain; charset=UTF-8\n"'."\n";
        $output .= '"Content-Transfer-Encoding: 8bit\n"'."\n";
        $output .= '"Language: '.$messages->getLocale().'\n"'."\n";
        $output .= '"Plural-Forms: '.$this->getPluralFormsHeader($messages->getLocale()).'\n"'."\n";
        $output .= "\n";

        $newLine = false;
        $isIntlDomain = str_ends_with($domain, MessageCatalogue::INTL_DOMAIN_SUFFIX);

        foreach ($messages->all($domain) as $source => $target) {
            if ($newLine) {
                $output .= "\n";
            } else {
                $newLine = true;
            }
            $metadata = $messages->getMetadata($source, $domain);

            if (isset($metadata['comments'])) {
                $output .= $this->formatComments($metadata['comments']);
            }
            if (isset($metadata['flags'])) {
                $output .= $this->formatComments(implode(',', (array) $metadata['flags']), ',');
            }
            if (isset($metadata['sources'])) {
                $output .= $this->formatComments(implode(' ', (array) $metadata['sources']), ':');
            }
            if (isset($metadata['context'])) {
                $output .= \sprintf('msgctxt "%s"'."\n", $this->escape($metadata['context']));
            }

            // in an ICU domain the pipe is an ordinary character, pluralization is expressed by the message itself
            $sourceRules = $isIntlDomain ? [] : $this->getStandardRules($source);
            $targetRules = $isIntlDomain ? [] : $this->getStandardRules($target);
            if (2 == \count($sourceRules) && $targetRules) {
                $output .= \sprintf('msgid "%s"'."\n", $this->escape($sourceRules[0]));
                $output .= \sprintf('msgid_plural "%s"'."\n", $this->escape($sourceRules[1]));
                foreach ($targetRules as $i => $targetRule) {
                    $output .= \sprintf('msgstr[%d] "%s"'."\n", $i, $this->escape($targetRule));
                }
            } else {
                $output .= \sprintf('msgid "%s"'."\n", $this->escape($source));
                $output .= \sprintf('msgstr "%s"'."\n", $this->escape($target));
            }
        }

        return $output;
    }

    /**
     * Returns the gettext "Plural-Forms" value matching the plural rule Symfony applies for the given
     * locale (see TranslatorTrait::getPluralizationRule()), so the header stays consistent by
     * construction with the msgstr indexes this dumper writes. It mirrors that method group by group,
     * including its single-form default for locales without a specific rule (e.g. ja, ko, zh).
     */
    private function getPluralFormsHeader(string $locale): string
    {
        $locale = 'pt_BR' !== $locale && 'en_US_POSIX' !== $locale && \strlen($locale) > 3
            ? substr($locale, 0, strrpos($locale, '_'))
            : $locale;

        return match ($locale) {
            'af',
            'bn',
            'bg',
            'ca',
            'da',
            'de',
            'el',
            'en',
            'en_US_POSIX',
            'eo',
            'es',
            'et',
            'eu',
            'fa',
            'fi',
            'fo',
            'fur',
            'fy',
            'gl',
            'gu',
            'ha',
            'he',
            'hu',
            'is',
            'it',
            'ku',
            'lb',
            'ml',
            'mn',
            'mr',
            'nah',
            'nb',
            'ne',
            'nl',
            'nn',
            'no',
            'oc',
            'om',
            'or',
            'pa',
            'pap',
            'ps',
            'pt',
            'so',
            'sq',
            'sv',
            'sw',
            'ta',
            'te',
            'tk',
            'ur',
            'zu' => 'nplurals=2; plural=(n != 1);',
            'am',
            'bh',
            'fil',
            'fr',
            'gun',
            'hi',
            'hy',
            'ln',
            'mg',
            'nso',
            'pt_BR',
            'ti',
            'wa' => 'nplurals=2; plural=(n > 1);',
            'be',
            'bs',
            'hr',
            'ru',
            'sh',
            'sr',
            'uk' => 'nplurals=3; plural=(n%10==1 && n%100!=11 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2);',
            'cs',
            'sk' => 'nplurals=3; plural=(n==1 ? 0 : n>=2 && n<=4 ? 1 : 2);',
            'ga' => 'nplurals=3; plural=(n==1 ? 0 : n==2 ? 1 : 2);',
            'lt' => 'nplurals=3; plural=(n%10==1 && n%100!=11 ? 0 : n%10>=2 && (n%100<10 || n%100>=20) ? 1 : 2);',
            'sl' => 'nplurals=4; plural=(n%100==1 ? 0 : n%100==2 ? 1 : n%100==3 || n%100==4 ? 2 : 3);',
            'mk' => 'nplurals=2; plural=(n%10==1 ? 0 : 1);',
            'mt' => 'nplurals=4; plural=(n==1 ? 0 : n==0 || (n%100>1 && n%100<11) ? 1 : n%100>10 && n%100<20 ? 2 : 3);',
            'lv' => 'nplurals=3; plural=(n==0 ? 0 : n%10==1 && n%100!=11 ? 1 : 2);',
            'pl' => 'nplurals=3; plural=(n==1 ? 0 : n%10>=2 && n%10<=4 && (n%100<12 || n%100>14) ? 1 : 2);',
            'cy' => 'nplurals=4; plural=(n==1 ? 0 : n==2 ? 1 : n==8 || n==11 ? 2 : 3);',
            'ro' => 'nplurals=3; plural=(n==1 ? 0 : n==0 || (n%100>0 && n%100<20) ? 1 : 2);',
            'ar' => 'nplurals=6; plural=(n==0 ? 0 : n==1 ? 1 : n==2 ? 2 : n%100>=3 && n%100<=10 ? 3 : n%100>=11 && n%100<=99 ? 4 : 5);',
            default => 'nplurals=1; plural=0;',
        };
    }

    private function getStandardRules(string $id): array
    {
        // Partly copied from TranslatorTrait::trans.
        $parts = [];
        if (preg_match('/^\|++$/', $id)) {
            $parts = explode('|', $id);
        } elseif (preg_match_all('/(?:\|\||[^\|])++/', $id, $matches)) {
            $parts = $matches[0];
        }

        $intervalRegexp = <<<'EOF'
            /^(?P<interval>
                ({\s*
                    (\-?\d+(\.\d+)?[\s*,\s*\-?\d+(\.\d+)?]*)
                \s*})

                    |

                (?P<left_delimiter>[\[\]])
                    \s*
                    (?P<left>-Inf|\-?\d+(\.\d+)?)
                    \s*,\s*
                    (?P<right>\+?Inf|\-?\d+(\.\d+)?)
                    \s*
                (?P<right_delimiter>[\[\]])
            )\s*(?P<message>.*?)$/xs
            EOF;

        $standardRules = [];
        foreach ($parts as $part) {
            $part = trim(str_replace('||', '|', $part));

            if (preg_match($intervalRegexp, $part)) {
                // Explicit rule is not a standard rule.
                return [];
            }

            $standardRules[] = $part;
        }

        return $standardRules;
    }

    protected function getExtension(): string
    {
        return 'po';
    }

    private function escape(string $str): string
    {
        return addcslashes($str, "\0..\37\42\134");
    }

    private function formatComments(string|array $comments, string $prefix = ''): ?string
    {
        $output = null;

        foreach ((array) $comments as $comment) {
            $output .= \sprintf('#%s %s'."\n", $prefix, $comment);
        }

        return $output;
    }
}
