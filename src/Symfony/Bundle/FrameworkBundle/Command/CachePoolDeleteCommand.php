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

use Symfony\Component\Cache\Command\CachePoolDeleteCommand as BaseCachePoolDeleteCommand;

trigger_deprecation('symfony/framework-bundle', '8.2', 'The "%s" class is deprecated, use "%s" instead.', 'Symfony\Bundle\FrameworkBundle\Command\CachePoolDeleteCommand', BaseCachePoolDeleteCommand::class);

class_alias(BaseCachePoolDeleteCommand::class, 'Symfony\Bundle\FrameworkBundle\Command\CachePoolDeleteCommand');
