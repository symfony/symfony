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
 * @author Abdellatif Ait Boudad <a.aitboudad@gmail.com>
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
     * {@inheritdoc}
     */
    public function isFresh($locale, array $options = array())
    {
        $cache = $this->configCacheFactory->cache($this->getCatalogueCachePath($locale, $options), function ($cache) {});

        return $cache->isFresh();
    }

    /**
     * {@inheritdoc}
     */
    public function load($locale, array $options = array())
    {
        $cache = $this->configCacheFactory->cache($this->getCatalogueCachePath($locale, $options), function ($cache) {});

        return include $cache->getPath();
    }

    /**
     * {@inheritdoc}
     */
    public function dump(MessageCatalogueInterface $messages, array $options = array())
    {
        $self = $this;
        $this->configCacheFactory->cache($this->getCatalogueCachePath($messages->getLocale(), $options),
            function (ConfigCacheInterface $cache) use ($self, $messages) {
                $self->dumpCatalogue($messages, $cache);
            }
        );
    }

    /**
     * This method is public because it needs to be callable from a closure in PHP 5.3. It should be made protected (or even private, if possible) in 3.0.
     *
     * @internal
     */
    public function dumpCatalogue($catalogue, ConfigCacheInterface $cache)
    {
        $fallbackContent = $this->getFallbackContent($catalogue);
        $content = sprintf(<<<EOF
<?php
use Symfony\Component\Translation\MessageCatalogue;
\$catalogue = new MessageCatalogue('%s', %s);
%s
return \$catalogue;
EOF
            ,
            $catalogue->getLocale(),
            var_export($catalogue->all(), true),
            $fallbackContent
        );
        $cache->write($content, $catalogue->getResources());
    }

    private function getFallbackContent(MessageCatalogue $catalogue)
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

        return sprintf('%s/catalogue.%s.%s.php', $this->cacheDir, $locale, $catalogueHash);
    }
}
