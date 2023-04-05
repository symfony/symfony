<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Transliterator;

use Symfony\Component\EmojiTransliterator\EmojiTransliterator as StandaloneEmojiTransliterator;

trigger_deprecation('symfony/intl', '6.3', 'The %s class is deprecated. Use %s instead', EmojiTransliterator::class, StandaloneEmojiTransliterator::class);

/**
 * @deprecated since Symfony 6.3. Use {@link \Symfony\Component\EmojiTransliterator\EmojiTransliterator} instead.
 */
final class EmojiTransliterator extends StandaloneEmojiTransliterator
{
}
