<?php

namespace Symfony\Component\Translation;

use Symfony\Component\Translation\Loader\LoaderInterface;

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
class Translator implements TranslatorInterface
{
    protected $catalogues;
    protected $locale;
    protected $fallbackLocale;
    protected $loaders;
    protected $resources;

    /**
     * Constructor.
     *
     * @param string $locale The locale
     */
    public function __construct($locale = null)
    {
        $this->locale = $locale;
        $this->loaders = array();
        $this->resources = array();
        $this->catalogues = array();
    }

    /**
     * Adds a Loader.
     *
     * @param string          $format The name of the loader (@see addResource())
     * @param LoaderInterface $loader A LoaderInterface instance
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
     */
    public function addResource($format, $resource, $locale, $domain = 'messages')
    {
        if (!isset($this->resources[$locale])) {
            $this->resources[$locale] = array();
        }

        $this->resources[$locale][] = array($format, $resource, $domain);
    }

    /**
     * {@inheritdoc}
     */
    public function setLocale($locale)
    {
        $this->locale = $locale;
    }

    /**
     * Sets the fallback locale.
     *
     * @param string $locale The fallback locale
     */
    public function setFallbackLocale($locale)
    {
        if (null !== $this->fallbackLocale) {
            // needed as the fallback locale is used to fill-in non-yet translated messages
            $this->catalogues = array();
        }

        $this->fallbackLocale = $locale;
    }

    /**
     * {@inheritdoc}
     */
    public function trans($id, array $parameters = array(), $domain = 'messages', $locale = null)
    {
        if (!isset($locale)) {
            $locale = $this->locale;
        }

        if (!isset($this->catalogues[$locale])) {
            $this->loadCatalogue($locale);
        }

        return strtr($this->catalogues[$locale]->getMessage($id, $domain), $parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function transChoice($id, $number, array $parameters = array(), $domain = 'messages', $locale = null)
    {
        if (!isset($locale)) {
            $locale = $this->locale;
        }

        if (!isset($this->catalogues[$locale])) {
            $this->loadCatalogue($locale);
        }

        return strtr($this->chooseMessage($this->catalogues[$locale]->getMessage($id, $domain), (int) $number, $locale), $parameters);
    }

    protected function chooseMessage($message, $number, $locale)
    {
        $parts = explode('|', $message);
        $explicitRules = array();
        $standardRules = array();
        foreach ($parts as $part) {
            $part = trim($part);

            if (preg_match('/^(?<range>'.Range::getRangeRegexp().')\s+(?<message>.+?)$/x', $part, $matches)) {
                $explicitRules[$matches['range']] = $matches['message'];
            } elseif (preg_match('/^\w+\: +(.+)$/', $part, $matches)) {
                $standardRules[] = $matches[1];
            } else {
                $standardRules[] = $part;
            }
        }

        // try to match an explicit rule, then fallback to the standard ones
        foreach ($explicitRules as $range => $m) {
            if (Range::test($number, $range)) {
                return $m;
            }
        }

        $position = PluralizationRules::get($number, $locale);
        if (!isset($standardRules[$position])) {
            throw new \InvalidArgumentException('Unable to choose a translation.');
        }

        return $standardRules[$position];
    }

    protected function loadCatalogue($locale)
    {
        if (isset($this->catalogues[$locale])) {
            return;
        }

        $this->catalogues[$locale] = new MessageCatalogue($locale);
        if (!isset($this->resources[$locale])) {
            return;
        }

        foreach ($this->resources[$locale] as $resource) {
            if (!isset($this->loaders[$resource[0]])) {
                throw new \RuntimeException(sprintf('The "%s" translation loader is not registered.', $resource[0]));
            }
            $this->catalogues[$locale]->addCatalogue($this->loaders[$resource[0]]->load($resource[1], $locale, $resource[2]));
        }

        $this->optimizeCatalogue($locale);
    }

    protected function optimizeCatalogue($locale)
    {
        if (strlen($locale) > 3) {
            $fallback = substr($locale, 0, -strlen(strrchr($locale, '_')));
        } else {
            $fallback = $this->fallbackLocale;
        }

        if (!isset($this->catalogues[$fallback])) {
            $this->loadCatalogue($fallback);
        }

        foreach ($this->catalogues[$fallback]->getResources() as $resource) {
            $this->catalogues[$locale]->addResource($resource);
        }
        foreach ($this->catalogues[$fallback]->getDomains() as $domain) {
            foreach ($this->catalogues[$fallback]->getMessages($domain) as $id => $translation) {
                if (false === $this->catalogues[$locale]->hasMessage($id, $domain)) {
                    $this->catalogues[$locale]->setMessage($id, $translation, $domain);
                }
            }
        }
    }
}
