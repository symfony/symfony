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

use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle as BaseAbstractBundle;

/**
 * A Bundle that provides configuration hooks.
 *
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 */
abstract class AbstractBundle extends Bundle
{
    public function getContainerExtension(): ?ExtensionInterface
    {
        return BaseAbstractBundle::getContainerExtension();
    }

    public function getPath(): string
    {
        return BaseAbstractBundle::getPath();
    }
}
