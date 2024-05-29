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

use Symfony\Component\Emoji\EmojiTransliterator as EmojiEmojiTransliterator;

trigger_deprecation('symfony/intl', '7.1', 'The "%s" class is deprecated, use "%s" instead.', EmojiTransliterator::class, EmojiEmojiTransliterator::class);

if (!class_exists(EmojiEmojiTransliterator::class)) {
    throw new \LogicException(sprintf('You cannot use the "%s" if the Emoji component is not available. Try running "composer require symfony/emoji".', EmojiEmojiTransliterator::class));
}

class_alias(EmojiEmojiTransliterator::class, EmojiTransliterator::class);

if (false) {
    /**
     * @deprecated since Symfony 7.1, use Symfony\Component\Emoji\EmojiTransliterator instead
     */
    class EmojiTransliterator extends \Transliterator
    {
    }
}
