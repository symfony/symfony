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

use Symfony\Component\Config\ConfigCacheInterface;
use Symfony\Component\Config\ConfigCacheFactoryInterface;
use Symfony\Component\Config\ConfigCacheFactory;

/**
 * @author Abdellatif Ait boudad <a.aitboudad@gmail.com>
 */
class MessageCache implements MessageCacheInterface
{
    /**
     * @var string
     */
    private $cacheDir;

    /**
     * @var bool
     */
    private $debug;

    /**
     * @var ConfigCacheFactoryInterface
     */
    private $configCacheFactory;

    /**
     * @param string                      $cacheDir
     * @param bool                        $debug
     * @param ConfigCacheFactoryInterface $configCacheFactory
     */
    public function __construct($cacheDir, $debug = false, ConfigCacheFactoryInterface $configCacheFactory = null)
    {
        $this->cacheDir = $cacheDir;
        $this->debug = $debug;

        if (null === $configCacheFactory) {
            $configCacheFactory = new ConfigCacheFactory($debug);
        }

        $this->configCacheFactory = $configCacheFactory;
    }

    /**
     * Sets the ConfigCache factory to use.
     *
     * @param ConfigCacheFactoryInterface $configCacheFactory
     */
    public function setConfigCacheFactory(ConfigCacheFactoryInterface $configCacheFactory)
    {
        trigger_error('The '.__METHOD__.' method is deprecated since version 2.8 and will be removed in 3.0.', E_USER_DEPRECATED);

        $this->configCacheFactory = $configCacheFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function cache($locale, array $options = array())
    {
        $defaultOptions = array(
            'resources' => array(),
            'fallback_locales' => array(),
            'initialize_catalogue' => function ($locale) {
                return new MessageCatalogue($locale);
            },
        );

        $options = array_merge($defaultOptions, $options);
        $self = $this; // required for PHP 5.3 where "$this" cannot be used in anonymous functions. Change in Symfony 3.0.
        $cache = $this->configCacheFactory->cache($this->getCatalogueCachePath($locale, $options),
            function (ConfigCacheInterface $cache) use ($self, $locale, $options) {
                $self->dumpCatalogue($locale, $options, $cache);
            }
        );

        /* Read catalogue from cache. */
        return include $cache->getPath();
    }

    /**
     * This method is public because it needs to be callable from a closure in PHP 5.3. It should be made protected (or even private, if possible) in 3.0.
     *
     * @internal
     */
    public function dumpCatalogue($locale, $options, ConfigCacheInterface $cache)
    {
        $catalogue = $options['initialize_catalogue']($locale);
        $fallbackContent = $this->getFallbackContent($catalogue, $options);
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
    }
    private function getFallbackContent(MessageCatalogue $catalogue, $options)
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

    private function getCatalogueCachePath($locale, $options)
    {
        $catalogueHash = sha1(serialize(array(
            'resources' => $options['resources'],
            'fallback_locales' => $options['fallback_locales'],
        )));

        return $this->cacheDir.'/catalogue.'.$locale.'.'.$catalogueHash.'.php';
    }
}
