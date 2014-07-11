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

use Symfony\Component\Translation\Translator as BaseTranslator;
use Symfony\Component\Translation\MessageSelector;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Config\ConfigCacheFactoryInterface;
use Symfony\Component\Config\DefaultConfigCacheFactory;
use Symfony\Component\Config\ConfigCacheInterface;

/**
 * Translator.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Translator extends BaseTranslator
{
    protected $container;
    protected $options = array(
        'cache_dir' => null,
        'debug'     => false,
    );
    protected $loaderIds;

    /**
     * @var ConfigCacheFactoryInterface|null
     */
    private $configCacheFactory;

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

        parent::__construct(null, $selector);
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

    /**
     * {@inheritdoc}
     */
    public function getLocale()
    {
        if (null === $this->locale && $request = $this->container->get('request_stack')->getCurrentRequest()) {
            $this->locale = $request->getLocale();
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

        $self = $this;
        $cache = $this->getConfigCacheFactory()->cache($this->options['cache_dir'].'/catalogue.'.$locale.'.php', function ($cache) use ($self, $locale) {
            $self->fillCache($cache, $locale);
        });

        if (!isset($this->catalogues[$locale])) {
            $this->catalogues[$locale] = include $cache;
        }

    }

    /* This method is only public to allow it to be called from a callback (prior PHP 5.4?) */
    public function fillCache(ConfigCacheInterface $cache, $locale)
    {
        $this->initialize();

        parent::loadCatalogue($locale);

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
    }

    protected function initialize()
    {
        foreach ($this->loaderIds as $id => $aliases) {
            foreach ($aliases as $alias) {
                $this->addLoader($alias, $this->container->get($id));
            }
        }
    }

    /**
     * Provides the ConfigCache factory implementation, falling back to a
     * default implementation if necessary.
     *
     * @return ConfigCacheFactoryInterface $configCacheFactory
     */
    private function getConfigCacheFactory()
    {
        if (!$this->configCacheFactory) {
            $this->configCacheFactory = new DefaultConfigCacheFactory($this->options['debug']);
        }

        return $this->configCacheFactory;
    }

}
