<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Translation;

use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;
use Symfony\Component\Translation\Translator as BaseTranslator;
use Symfony\Component\Translation\MessageSelector;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Translator.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Translator extends BaseTranslator implements WarmableInterface
{
    protected $container;
    protected $loaderIds;

    protected $options = array(
        'cache_dir' => null,
        'debug' => false,
        'resource_files' => array(),
    );

    /**
     * @var array
     */
    private $resourceLocales;

    /**
     * Constructor.
     *
     * Available options:
     *
     *   * cache_dir: The cache directory (or null to disable caching)
     *   * debug:     Whether to enable debugging or not (false by default)
     *   * resource_files: List of translation resources available grouped by locale.
     *
     * @param ContainerInterface $container A ContainerInterface instance
     * @param MessageSelector    $selector  The message selector for pluralization
     * @param array              $loaderIds An array of loader Ids
     * @param array              $options   An array of options
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(ContainerInterface $container, MessageSelector $selector, $loaderIds = array(), array $options = array())
    {
        $this->container = $container;
        $this->loaderIds = $loaderIds;

        // check option names
        if ($diff = array_diff(array_keys($options), array_keys($this->options))) {
            throw new \InvalidArgumentException(sprintf('The Translator does not support the following options: \'%s\'.', implode('\', \'', $diff)));
        }

        $this->options = array_merge($this->options, $options);
        $this->resourceLocales = array_keys($this->options['resource_files']);
        if (null !== $this->options['cache_dir'] && $this->options['debug']) {
            $this->loadResources();
        }

        parent::__construct(null, $selector, $this->options['cache_dir'], $this->options['debug']);
    }

    /**
     * {@inheritdoc}
     */
    public function warmUp($cacheDir)
    {
        foreach ($this->resourceLocales as $locale) {
            $this->loadCatalogue($locale);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function initializeCatalogue($locale)
    {
        $this->initialize();
        parent::initializeCatalogue($locale);
    }

    protected function initialize()
    {
        $this->loadResources();
        foreach ($this->loaderIds as $id => $aliases) {
            foreach ($aliases as $alias) {
                $this->addLoader($alias, $this->container->get($id));
            }
        }
    }

    private function loadResources()
    {
        foreach ($this->options['resource_files'] as $locale => $files) {
            foreach ($files as $key => $file) {
                // filename is domain.locale.format
                list($domain, $locale, $format) = explode('.', basename($file), 3);
                $this->addResource($format, $file, $locale, $domain);
                unset($this->options['resource_files'][$locale][$key]);
            }
        }
    }
}
