<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

/**
 * Registers the cache warmers.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class AddCacheWarmerPass implements CompilerPassInterface
{
    use PriorityTaggedServiceTrait;

    private $tag;
    private $serviceName;

    public function __construct($serviceName, $tag)
    {
        $this->serviceName = $serviceName;
        $this->tag = $tag;
    }

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition($this->serviceName)) {
            return;
        }

        $warmers = $this->findAndSortTaggedServices($this->tag, $container);

        if (empty($warmers)) {
            return;
        }

        $container->getDefinition($this->serviceName)->replaceArgument(0, $warmers);
    }
}
