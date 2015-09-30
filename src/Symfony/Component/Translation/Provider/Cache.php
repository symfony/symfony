<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Provider;

use Symfony\Component\Config\ConfigCacheFactory;
use Symfony\Component\Config\ConfigCacheFactoryInterface;
use Symfony\Component\Config\ConfigCacheInterface;
use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\MessageCatalogueInterface;

/**
 * Caches the results provided by a MessageCatalogueProviderInterface
 * in a ConfigCacheInterface instance.
 *
 * Registered resources are tracked so different cache locations can be
 * used for different resource sets.
 *
 * @author Matthias Pigulla <mp@webfactory.de>
 */
class Cache implements MessageCatalogueProviderInterface
{
    /**
     * @var string
     */
    private $cacheDir;

    /**
     * @var ConfigCacheFactoryInterface|null
     */
    private $configCacheFactory;

    /**
     * @var array
     */
    private $resources = array();

    /**
     * @var MessageCatalogueProviderInterface
     */
    private $decorated;

    public function __construct(MessageCatalogueProviderInterface $decorated, $cacheDir, $debug)
    {
        $this->decorated = $decorated;
        $this->cacheDir = $cacheDir;
        $this->configCacheFactory = new ConfigCacheFactory($debug);
    }

    /**
     * Sets the ConfigCache factory to use.
     *
     * @param ConfigCacheFactoryInterface $configCacheFactory
     */
    public function setConfigCacheFactory(ConfigCacheFactoryInterface $configCacheFactory)
    {
        $this->configCacheFactory = $configCacheFactory;
    }

    public function addLoader($format, LoaderInterface $loader)
    {
        $this->decorated->addLoader($format, $loader);
    }

    public function getLoaders()
    {
        return $this->decorated->getLoaders();
    }

    public function addResource($format, $resource, $locale, $domain = null)
    {
        // track resources for cache file path only
        $this->resources[$locale][] = array($format, $resource, $domain ?: 'messages');
        $this->decorated->addResource($format, $resource, $locale, $domain);
    }

    public function provideCatalogue($locale, $fallbackLocales = array())
    {
        $tmpCatalogue = null;

        $self = $this; // required for PHP 5.3 where "$this" cannot be use()d in anonymous functions. Change in Symfony 3.0.
        $cache = $this->configCacheFactory->cache($this->getCatalogueCachePath($locale, $fallbackLocales),
            function (ConfigCacheInterface $cache) use ($self, $locale, $fallbackLocales, &$tmpCatalogue) {
                $tmpCatalogue = $self->dumpCatalogue($locale, $fallbackLocales, $cache);
            }
        );

        if ($tmpCatalogue !== null) {
            /* Catalogue has been initialized as it was written out to cache. */
            return $tmpCatalogue;
        }

        /* Read catalogue from cache. */
        return include $cache->getPath();
    }

    /**
     * This method is public because it needs to be callable from a closure in PHP 5.3. It should be made protected (or even private, if possible) in 3.0.
     *
     * @internal
     */
    public function dumpCatalogue($locale, $fallbackLocales, ConfigCacheInterface $cache)
    {
        $catalogue = $this->decorated->provideCatalogue($locale, $fallbackLocales);
        $fallbackContent = $this->getFallbackContent($catalogue);

        $content = sprintf(<<<EOF
<?php

use Symfony\Component\Translation\MessageCatalogue;

\$catalogue = new MessageCatalogue('%s', %s);

%s
return \$catalogue;

EOF
            ,
            $locale,
            var_export($catalogue->all(), true),
            $fallbackContent
        );

        $cache->write($content, $catalogue->getResources());

        return $catalogue;
    }

    private function getFallbackContent(MessageCatalogueInterface $catalogue)
    {
        $fallbackContent = '';
        $current = '';
        $replacementPattern = '/[^a-z0-9_]/i';
        $fallbackCatalogue = $catalogue->getFallbackCatalogue();
        while ($fallbackCatalogue) {
            $fallback = $fallbackCatalogue->getLocale();
            $fallbackSuffix = ucfirst(preg_replace($replacementPattern, '_', $fallback));
            $currentSuffix = ucfirst(preg_replace($replacementPattern, '_', $current));

            $fallbackContent .= sprintf(<<<EOF
\$catalogue%s = new MessageCatalogue('%s', %s);
\$catalogue%s->addFallbackCatalogue(\$catalogue%s);

EOF
                ,
                $fallbackSuffix,
                $fallback,
                var_export($fallbackCatalogue->all(), true),
                $currentSuffix,
                $fallbackSuffix
            );
            $current = $fallbackCatalogue->getLocale();
            $fallbackCatalogue = $fallbackCatalogue->getFallbackCatalogue();
        }

        return $fallbackContent;
    }

    private function getCatalogueCachePath($locale, $fallbackLocales)
    {
        $catalogueHash = sha1(serialize(array(
            'resources' => isset($this->resources[$locale]) ? $this->resources[$locale] : array(),
            'fallback_locales' => $fallbackLocales,
        )));

        return $this->cacheDir.'/catalogue.'.$locale.'.'.$catalogueHash.'.php';
    }
}
