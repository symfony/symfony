<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\MessageCatalogueProvider;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * MessageCatalogueProvider loads catalogue from resources with
 * lazily loads loaders from the dependency injection container.
 *
 * @author Abdellatif Ait boudad <a.aitboudad@gmail.com>
 */
class ContainerAwareMessageCatalogueProvider extends MessageCatalogueProvider
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var array
     */
    private $loaderIds;

    /**
     * @var array
     */
    private $fileResources;

    /**
     * @param ContainerInterface $container       A ContainerInterface instance
     * @param array              $loaderIds
     * @param array              $fileResources
     * @param array              $fallbackLocales The fallback locales.
     */
    public function __construct(ContainerInterface $container, $loaderIds, $fileResources, $fallbackLocales = array())
    {
        $this->container = $container;
        $this->loaderIds = $loaderIds;
        $this->fileResources = $fileResources;
        $this->setFallbackLocales($fallbackLocales);
    }

    /**
     * {@inheritdoc}
     */
    public function getLoaders()
    {
        foreach ($this->loaderIds as $id => $aliases) {
            foreach ($aliases as $alias) {
                $this->addLoader($alias, $this->container->get($id));
            }
        }

        return parent::getLoaders();
    }

    /**
     * @return array
     */
    public function getResources()
    {
        foreach ($this->fileResources as $key => $resource) {
            $this->addResource($resource[0], $resource[1], $resource[2], isset($resource[3]) ? $resource[3] : null);
            unset($this->fileResources[$key]);
        }

        return parent::getResources();
    }
}
