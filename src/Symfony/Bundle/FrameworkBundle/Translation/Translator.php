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
use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\MessageSelector;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Session;
use Symfony\Component\Config\ConfigCache;

/**
 * Translator.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Translator extends BaseTranslator
{
    protected $container;
    protected $options;
    protected $session;
    protected $loaderIds;

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
     * @param Session            $session   A Session instance
     */
    public function __construct(ContainerInterface $container, MessageSelector $selector, $loaderIds = array(), array $options = array(), Session $session = null)
    {
        parent::__construct(null, $selector);

        $this->session = $session;
        $this->container = $container;
        $this->loaderIds = $loaderIds;

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

        $cache = new ConfigCache($this->options['cache_dir'].'/catalogue.'.$locale.'.php', $this->options['debug']);
        if (!$cache->isFresh()) {
            $this->initialize();

            parent::loadCatalogue($locale);

            $content = sprintf(
                "<?php use Symfony\Component\Translation\MessageCatalogue; return new MessageCatalogue('%s', %s);",
                $locale,
                var_export($this->catalogues[$locale]->all(), true)
            );

            $cache->write($content, $this->catalogues[$locale]->getResources());

            return;
        }

        $this->catalogues[$locale] = include $cache;
    }

    protected function initialize()
    {
        foreach ($this->loaderIds as $id => $alias) {
            $this->addLoader($alias, $this->container->get($id));
        }
    }
}
