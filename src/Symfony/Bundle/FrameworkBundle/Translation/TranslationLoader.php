<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Translation;

@trigger_error(sprintf('The class "%s" has been deprecated. Use "%s" instead. ', self::class, \Symfony\Component\Translation\Loader\TranslationLoader::class), E_USER_DEPRECATED);

/**
 * @deprecated Class moved to Symfony\Component\Translation\Loader\TranslationLoader
 */
class TranslationLoader extends \Symfony\Component\Translation\Loader\TranslationLoader
{
}
