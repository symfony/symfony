<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Command;

use Symfony\Component\Cache\Command\CachePoolInvalidateTagsCommand as BaseCachePoolInvalidateTagsCommand;

trigger_deprecation('symfony/framework-bundle', '8.2', 'The "%s" class is deprecated, use "%s" instead.', 'Symfony\Bundle\FrameworkBundle\Command\CachePoolInvalidateTagsCommand', BaseCachePoolInvalidateTagsCommand::class);

class_alias(BaseCachePoolInvalidateTagsCommand::class, 'Symfony\Bundle\FrameworkBundle\Command\CachePoolInvalidateTagsCommand');
