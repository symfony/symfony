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

use Symfony\Component\Validator\CacheWarmer\ValidatorCacheWarmer as BaseValidatorCacheWarmer;

trigger_deprecation('symfony/framework-bundle', '8.2', 'The "%s" class is deprecated, use "%s" instead.', 'Symfony\Bundle\FrameworkBundle\CacheWarmer\ValidatorCacheWarmer', BaseValidatorCacheWarmer::class);

class_alias(BaseValidatorCacheWarmer::class, 'Symfony\Bundle\FrameworkBundle\CacheWarmer\ValidatorCacheWarmer');
