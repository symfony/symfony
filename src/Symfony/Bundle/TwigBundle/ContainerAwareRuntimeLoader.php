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

@trigger_error(sprintf('The %s class is deprecated since version 3.3 and will be removed in 4.0. Use the Twig\RuntimeLoader\ContainerRuntimeLoader class instead.', ContainerAwareRuntimeLoader::class), E_USER_DEPRECATED);

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Twig\RuntimeLoader\RuntimeLoaderInterface;

/**
 * Loads Twig extension runtimes via the service container.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @deprecated since version 3.3, will be removed in 4.0. Use \Twig\Loader\ContainerRuntimeLoader instead.
 */
class ContainerAwareRuntimeLoader implements RuntimeLoaderInterface
{
    private $container;
    private $mapping;
    private $logger;

    public function __construct(ContainerInterface $container, array $mapping, LoggerInterface $logger = null)
    {
        $this->container = $container;
        $this->mapping = $mapping;
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function load($class)
    {
        if (isset($this->mapping[$class])) {
            return $this->container->get($this->mapping[$class]);
        }

        if (null !== $this->logger) {
            $this->logger->warning(sprintf('Class "%s" is not configured as a Twig runtime. Add the "twig.runtime" tag to the related service in the container.', $class));
        }
    }
}
