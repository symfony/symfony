<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Loads Twig extension runtimes.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ContainerAwareRuntimeLoader implements \Twig_RuntimeLoaderInterface
{
    private $container;
    private $mapping;

    public function __construct(ContainerInterface $container, array $mapping)
    {
        $this->mapping = $mapping;
    }

    /**
     * {@inheritdoc}
     */
    public function load($class)
    {
        if (!isset($this->mapping[$class])) {
            throw new \LogicException(sprintf('Class "%s" is not configured as a Twig runtime. Add the "twig.runtime" tag to the related service in the container.', $class));
        }

        return $this->container->get($this->mapping[$class]);
    }
}
