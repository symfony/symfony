<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Config;

use Symfony\Component\DependencyInjection\Kernel\FileLocator as BaseFileLocator;

trigger_deprecation('symfony/http-kernel', '8.1', 'The "%s" class is deprecated, use "%s" instead.', FileLocator::class, BaseFileLocator::class);

/**
 * @deprecated since Symfony 8.1, use Symfony\Component\DependencyInjection\Kernel\FileLocator instead
 */
class FileLocator extends BaseFileLocator
{
}
