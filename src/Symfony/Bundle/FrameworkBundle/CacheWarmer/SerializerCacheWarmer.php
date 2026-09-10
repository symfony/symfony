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

use Symfony\Component\Serializer\CacheWarmer\SerializerCacheWarmer as BaseSerializerCacheWarmer;

trigger_deprecation('symfony/framework-bundle', '8.2', 'The "%s" class is deprecated, use "%s" instead.', 'Symfony\Bundle\FrameworkBundle\CacheWarmer\SerializerCacheWarmer', BaseSerializerCacheWarmer::class);

class_alias(BaseSerializerCacheWarmer::class, 'Symfony\Bundle\FrameworkBundle\CacheWarmer\SerializerCacheWarmer');
