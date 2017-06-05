<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig;

/**
 * Loads Twig extension runtimes.
 *
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
class TwigRuntimeLoader implements \Twig_RuntimeLoaderInterface
{
    private $mapping;

    public function __construct(array $mapping)
    {
        $this->mapping = $mapping;
    }

    /**
     * {@inheritdoc}
     */
    public function load($class)
    {
        if (isset($this->mapping[$class])) {
            return $this->mapping[$class];
        }

        throw new \InvalidArgumentException(sprintf('Class "%s" is not mapped as a Twig runtime.', $class));
    }
}
