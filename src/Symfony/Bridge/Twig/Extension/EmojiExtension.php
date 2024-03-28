<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Extension;

use Symfony\Component\Emoji\EmojiTransliterator;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
final class EmojiExtension extends AbstractExtension
{
    private static array $transliterators = [];

    public function getFilters(): array
    {
        return [
            new TwigFilter('emojify', $this->emojify(...)),
        ];
    }

    public function emojify(string $string, string $catalog = 'slack'): string
    {
        if (!in_array($catalog, $catalogs = ['slack', 'github'], true)) {
            throw new \InvalidArgumentException(sprintf('The catalog "%s" is not supported. Try one among "%s".', $catalog, implode('", "', $catalogs)));
        }

        $tr = self::$transliterators[$catalog] ??= EmojiTransliterator::create('emoji-'.$catalog, EmojiTransliterator::REVERSE);

        return (string) $tr->transliterate($string);
    }
}
