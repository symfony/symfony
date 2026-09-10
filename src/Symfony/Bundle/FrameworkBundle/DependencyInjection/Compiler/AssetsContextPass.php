<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler;

use Symfony\Component\Asset\DependencyInjection\AssetsContextPass as BaseAssetsContextPass;

trigger_deprecation('symfony/framework-bundle', '8.2', 'The "%s" class is deprecated, use "%s" instead.', 'Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\AssetsContextPass', BaseAssetsContextPass::class);

class_alias(BaseAssetsContextPass::class, 'Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\AssetsContextPass');
