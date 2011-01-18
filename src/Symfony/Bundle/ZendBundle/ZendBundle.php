<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\ZendBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Bundle\ZendBundle\DependencyInjection\Compiler\ZendLoggerWriterPass;

/**
 * Bundle.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class ZendBundle extends Bundle
{
    public function registerExtensions(ContainerBuilder $container)
    {
        parent::registerExtensions($container);

        $container->addCompilerPass(new ZendLoggerWriterPass());
    }

    /**
     * {@inheritdoc}
     */
    public function getNamespace()
    {
        return __NAMESPACE__;
    }

    /**
     * {@inheritdoc}
     */
    public function getPath()
    {
        return strtr(__DIR__, '\\', '/');
    }
}
