<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation;

use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\Exception\NotFoundResourceException;
use Symfony\Component\Config\ConfigCache;

/**
 * Translator.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @api
 */
class Translator implements TranslatorInterface
{
    /**
     * @var MessageCatalogueInterface[]
     */
    protected $catalogues = array();

    /**
     * @var string
     */
    protected $locale;

    /**
     * @var array
     */
    private $fallbackLocales = array();

    /**
     * @var LoaderInterface[]
     */
    private $loaders = array();

    /**
     * @var array
     */
    private $resources = array();

    /**
     * @var MessageSelector
     */
    private $selector;

    /**
     * @var array
     */
    private $options = array();

    /**
     * Constructor.
     *
     * @param string               $locale   The locale
     * @param MessageSelector|null $selector The message selector for pluralization
     * @param array                $options  An array of options
     *
     * @api
     */
    public function __construct($locale, MessageSelector $selector = null, array $options = array())
    {
        $this->locale = $locale;
        $this->selector = $selector ?: new MessageSelector();
        $this->setOptions($options);
    }

    /**
     * Adds a Loader.
     *
     * @param string          $format The name of the loader (@see addResource())
     * @param LoaderInterface $loader A LoaderInterface instance
     *
     * @api
     */
    public function addLoader($format, LoaderInterface $loader)
    {
        $this->loaders[$format] = $loader;
    }

    /**
     * Adds a Resource.
     *
     * @param string $format   The name of the loader (@see addLoader())
     * @param mixed  $resource The resource name
     * @param string $locale   The locale
     * @param string $domain   The domain
     *
     * @api
     */
    public function addResource($format, $resource, $locale, $domain = null)
    {
        if (null === $domain) {
            $domain = 'messages';
        }

        $this->resources[$locale][] = array($format, $resource, $domain);

        if (in_array($locale, $this->fallbackLocales)) {
            $this->catalogues = array();
        } else {
            unset($this->catalogues[$locale]);
        }
    }

    /**
     * {@inheritdoc}
     *
     * @api
     */
    public function setLocale($locale)
    {
        $this->locale = $locale;
    }

    /**
     * {@inheritdoc}
     *
     * @api
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * Sets the fallback locale(s).
     *
     * @param string|array $locales The fallback locale(s)
     *
     * @deprecated since 2.3, to be removed in 3.0. Use setFallbackLocales() instead.
     *
     * @api
     */
    public function setFallbackLocale($locales)
    {
        $this->setFallbackLocales(is_array($locales) ? $locales : array($locales));
    }

    /**
     * Sets the fallback locales.
     *
     * @param array $locales The fallback locales
     *
     * @api
     */
    public function setFallbackLocales(array $locales)
    {
        // needed as the fallback locales are linked to the already loaded catalogues
        $this->catalogues = array();

        $this->fallbackLocales = $locales;
    }

    /**
     * Gets the fallback locales.
     *
     * @return array $locales The fallback locales
     *
     * @api
     */
    public function getFallbackLocales()
    {
        return $this->fallbackLocales;
    }

    /**
     * {@inheritdoc}
     *
     * @api
     */
    public function trans($id, array $parameters = array(), $domain = null, $locale = null)
    {
        if (null === $locale) {
            $locale = $this->getLocale();
        }

        if (null === $domain) {
            $domain = 'messages';
        }

        if (!isset($this->catalogues[$locale])) {
            $this->loadCatalogue($locale);
        }

        return strtr($this->catalogues[$locale]->get((string) $id, $domain), $parameters);
    }

    /**
     * {@inheritdoc}
     *
     * @api
     */
    public function transChoice($id, $number, array $parameters = array(), $domain = null, $locale = null)
    {
        if (null === $locale) {
            $locale = $this->getLocale();
        }

        if (null === $domain) {
            $domain = 'messages';
        }

        if (!isset($this->catalogues[$locale])) {
            $this->loadCatalogue($locale);
        }

        $id = (string) $id;

        $catalogue = $this->catalogues[$locale];
        while (!$catalogue->defines($id, $domain)) {
            if ($cat = $catalogue->getFallbackCatalogue()) {
                $catalogue = $cat;
                $locale = $catalogue->getLocale();
            } else {
                break;
            }
        }

        return strtr($this->selector->choose($catalogue->get($id, $domain), (int) $number, $locale), $parameters);
    }

    /**
     * Sets options.
     *
     * Available options:
     *
     *   * cache_dir:     The cache directory (or null to disable caching)
     *   * debug:         Whether to enable debugging or not (false by default)
     *
     * @param array $options An array of options
     *
     * @throws \InvalidArgumentException When unsupported option is provided
     */
    public function setOptions(array $options)
    {
        $this->options = array(
            'cache_dir'              => null,
            'debug'                  => false,
        );

        // check option names and live merge, if errors are encountered Exception will be thrown
        $invalid = array();
        foreach ($options as $key => $value) {
            if (array_key_exists($key, $this->options)) {
                $this->options[$key] = $value;
            } else {
                $invalid[] = $key;
            }
        }

        if ($invalid) {
            throw new \InvalidArgumentException(sprintf('The Translator does not support the following options: "%s".', implode('", "', $invalid)));
        }
    }

    /**
     * Sets an option.
     *
     * @param string $key   The key
     * @param mixed  $value The value
     *
     * @throws \InvalidArgumentException
     */
    public function setOption($key, $value)
    {
        if (!array_key_exists($key, $this->options)) {
            throw new \InvalidArgumentException(sprintf('The Translator does not support the "%s" option.', $key));
        }

        $this->options[$key] = $value;
    }

    /**
     * Gets an option value.
     *
     * @param string $key The key
     *
     * @return mixed The value
     *
     * @throws \InvalidArgumentException
     */
    public function getOption($key)
    {
        if (!array_key_exists($key, $this->options)) {
            throw new \InvalidArgumentException(sprintf('The Translator does not support the "%s" option.', $key));
        }

        return $this->options[$key];
    }

    /**
     * Gets the loaders.
     *
     * @return array LoaderInterface[]
     */
    protected function getLoaders()
    {
        return $this->loaders;
    }

    /**
     * @param string $locale
     */
    protected function loadCatalogue($locale)
    {
        if (null === $this->options['cache_dir']) {
            $this->initializeCatalogue($locale);
        } else {
            $this->initializeCacheCatalogue($locale);
        }
    }

    /**
     * @param string $locale
     */
    protected function initializeCatalogue($locale)
    {
        try {
            $this->doLoadCatalogue($locale);
        } catch (NotFoundResourceException $e) {
            if (!$this->computeFallbackLocales($locale)) {
                throw $e;
            }
        }
        $this->loadFallbackCatalogues($locale);
    }

    /**
     * @param string $locale
     */
    private function initializeCacheCatalogue($locale)
    {
        $cache = new ConfigCache($this->options['cache_dir'].'/catalogue.'.$locale.'.php', $this->options['debug']);
        if (!$cache->isFresh()) {
            $this->initializeCatalogue($locale);

            $fallbackContent = '';
            $current = '';
            foreach ($this->computeFallbackLocales($locale) as $fallback) {
                $fallbackSuffix = ucfirst(str_replace('-', '_', $fallback));

                $fallbackContent .= sprintf(<<<EOF
\$catalogue%s = new MessageCatalogue('%s', %s);
\$catalogue%s->addFallbackCatalogue(\$catalogue%s);


EOF
                    ,
                    $fallbackSuffix,
                    $fallback,
                    var_export($this->catalogues[$fallback]->all(), true),
                    ucfirst(str_replace('-', '_', $current)),
                    $fallbackSuffix
                );
                $current = $fallback;
            }

            $content = sprintf(<<<EOF
<?php

use Symfony\Component\Translation\MessageCatalogue;

\$catalogue = new MessageCatalogue('%s', %s);

%s
return \$catalogue;

EOF
                ,
                $locale,
                var_export($this->catalogues[$locale]->all(), true),
                $fallbackContent
            );

            $cache->write($content, $this->catalogues[$locale]->getResources());

            return;
        }

        $this->catalogues[$locale] = include $cache;
    }

    private function doLoadCatalogue($locale)
    {
        $this->catalogues[$locale] = new MessageCatalogue($locale);

        if (isset($this->resources[$locale])) {
            foreach ($this->resources[$locale] as $resource) {
                if (!isset($this->loaders[$resource[0]])) {
                    throw new \RuntimeException(sprintf('The "%s" translation loader is not registered.', $resource[0]));
                }
                $this->catalogues[$locale]->addCatalogue($this->loaders[$resource[0]]->load($resource[1], $locale, $resource[2]));
            }
        }
    }

    private function loadFallbackCatalogues($locale)
    {
        $current = $this->catalogues[$locale];

        foreach ($this->computeFallbackLocales($locale) as $fallback) {
            if (!isset($this->catalogues[$fallback])) {
                $this->doLoadCatalogue($fallback);
            }

            $current->addFallbackCatalogue($this->catalogues[$fallback]);
            $current = $this->catalogues[$fallback];
        }
    }

    protected function computeFallbackLocales($locale)
    {
        $locales = array();
        foreach ($this->fallbackLocales as $fallback) {
            if ($fallback === $locale) {
                continue;
            }

            $locales[] = $fallback;
        }

        if (strrchr($locale, '_') !== false) {
            array_unshift($locales, substr($locale, 0, -strlen(strrchr($locale, '_'))));
        }

        return array_unique($locales);
    }
}
