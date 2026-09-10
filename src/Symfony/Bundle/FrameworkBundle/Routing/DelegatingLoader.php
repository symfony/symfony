<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Routing;

use Symfony\Component\Routing\Loader\DelegatingLoader as BaseDelegatingLoader;

trigger_deprecation('symfony/framework-bundle', '8.2', 'The "%s" class is deprecated, use "%s" instead.', 'Symfony\Bundle\FrameworkBundle\Routing\DelegatingLoader', BaseDelegatingLoader::class);

class_alias(BaseDelegatingLoader::class, 'Symfony\Bundle\FrameworkBundle\Routing\DelegatingLoader');
