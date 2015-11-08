<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Extension;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Loads Twig extension runtimes.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ContainerAwareRuntimeLoader implements \Twig_RuntimeLoaderInterface
{
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * {@inheritdoc}
     */
    public function load($name)
    {
        $id = 'twig.extension.runtime.'.$name;

        if ($this->container->has($id)) {
            return $this->container->get($id);
        }
    }
}
