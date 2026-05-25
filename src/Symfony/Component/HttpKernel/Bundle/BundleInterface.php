<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Bundle;

use Symfony\Component\DependencyInjection\Kernel\BundleInterface as BaseBundleInterface;

/**
 * @deprecated since Symfony 8.1, use Symfony\Component\DependencyInjection\Kernel\BundleInterface instead
 */
interface BundleInterface extends BaseBundleInterface
{
    public function getNamespace(): string;
}
