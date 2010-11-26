<?php

namespace Symfony\Bundle\FrameworkBundle\Translation;

use Symfony\Component\Translation\Translator as BaseTranslator;
use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\MessageSelector;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Session;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Translator.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class Translator extends BaseTranslator
{
    protected $container;
    protected $options;
    protected $session;

    /**
     * Constructor.
     *
     * Available options:
     *
     *   * cache_dir: The cache directory (or null to disable caching)
     *   * debug:     Whether to enable debugging or not (false by default)
     *
     * @param ContainerInterface $container A ContainerInterface instance
     * @param MessageSelector    $selector  The message selector for pluralization
     * @param array              $options   An array of options
     * @param Session            $session   A Session instance
     */
    public function __construct(ContainerInterface $container, MessageSelector $selector, array $options = array(), Session $session = null)
    {
        parent::__construct(null, $selector);

        $this->session = $session;
        $this->container = $container;

        $this->options = array(
            'cache_dir' => null,
            'debug'     => false,
        );

        // check option names
        if ($diff = array_diff(array_keys($options), array_keys($this->options))) {
            throw new \InvalidArgumentException(sprintf('The Router does not support the following options: \'%s\'.', implode('\', \'', $diff)));
        }

        $this->options = array_merge($this->options, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function getLocale()
    {
        if (null === $this->locale && null !== $this->session) {
            $this->locale = $this->session->getLocale();
        }

        return $this->locale;
    }

    /**
     * {@inheritdoc}
     */
    protected function loadCatalogue($locale)
    {
        if (isset($this->catalogues[$locale])) {
            return;
        }

        if (null === $this->options['cache_dir']) {
            $this->initialize();

            return parent::loadCatalogue($locale);
        }

        if ($this->needsReload($locale)) {
            $this->initialize();

            parent::loadCatalogue($locale);

            $this->updateCache($locale);

            return;
        }

        $this->catalogues[$locale] = include $this->getCacheFile($locale);
    }

    protected function initialize()
    {
        foreach ($this->container->findTaggedServiceIds('translation.loader') as $id => $attributes) {
            $this->addLoader($attributes[0]['alias'], $this->container->get($id));
        }

        foreach ($this->container->getParameter('translation.resources') as $resource) {
            $this->addResource($resource[0], $resource[1], $resource[2], $resource[3]);
        }
    }

    protected function updateCache($locale)
    {
        $this->writeCacheFile($this->getCacheFile($locale), sprintf(
            "<?php use Symfony\Component\Translation\MessageCatalogue; return new MessageCatalogue('%s', %s);",
            $locale,
            var_export($this->catalogues[$locale]->all(), true)
        ));

        if ($this->options['debug']) {
            $this->writeCacheFile($this->getCacheFile($locale, 'meta'), serialize($this->catalogues[$locale]->getResources()));
        }
    }

    protected function needsReload($locale)
    {
        $file = $this->getCacheFile($locale);
        if (!file_exists($file)) {
            return true;
        }

        if (!$this->options['debug']) {
            return false;
        }

        $metadata = $this->getCacheFile($locale, 'meta');
        if (!file_exists($metadata)) {
            return true;
        }

        $time = filemtime($file);
        $meta = unserialize(file_get_contents($metadata));
        foreach ($meta as $resource) {
            if (!$resource->isUptodate($time)) {
                return true;
            }
        }

        return false;
    }

    protected function getCacheFile($locale, $extension = 'php')
    {
        return $this->options['cache_dir'].'/catalogue.'.$locale.'.'.$extension;
    }

    /**
     * @throws \RuntimeException When cache file can't be wrote
     */
    protected function writeCacheFile($file, $content)
    {
        if (!is_dir(dirname($file))) {
            @mkdir(dirname($file), 0777, true);
        }

        $tmpFile = tempnam(dirname($file), basename($file));
        if (false !== @file_put_contents($tmpFile, $content) && @rename($tmpFile, $file)) {
            chmod($file, 0644);

            return;
        }

        throw new \RuntimeException(sprintf('Failed to write cache file "%s".', $file));
    }
}
