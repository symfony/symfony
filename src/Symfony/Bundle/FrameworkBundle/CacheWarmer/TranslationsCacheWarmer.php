<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\CacheWarmer;

use Symfony\Component\Translation\CacheWarmer\TranslationsCacheWarmer as BaseTranslationsCacheWarmer;

trigger_deprecation('symfony/framework-bundle', '8.2', 'The "%s" class is deprecated, use "%s" instead.', 'Symfony\Bundle\FrameworkBundle\CacheWarmer\TranslationsCacheWarmer', BaseTranslationsCacheWarmer::class);

class_alias(BaseTranslationsCacheWarmer::class, 'Symfony\Bundle\FrameworkBundle\CacheWarmer\TranslationsCacheWarmer');
