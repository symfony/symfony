<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\ResourceBundle;

use Symfony\Component\Icu\LanguageDataProvider;
use Symfony\Component\Intl\Exception\NoSuchEntryException;
use Symfony\Component\Intl\Locale;
use Symfony\Component\Intl\ResourceBundle\Reader\BundleEntryReaderInterface;

/**
 * Default implementation of {@link LanguageBundleInterface}.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @deprecated Deprecated since version 2.5, to be removed in Symfony 3.0.
 *             Use {@link LanguageDataProvider} instead.
 */
class LanguageBundle extends AbstractBundle implements LanguageBundleInterface
{
    /**
     * @var LanguageDataProvider
     */
    private $languageDataProvider;

    /**
     * Creates a bundle at the given path using the given reader for reading
     * bundle entries.
     *
     * @param string                     $path   The path to the resource bundle.
     * @param BundleEntryReaderInterface $reader The reader for reading the resource bundle.
     */
    public function __construct($path, BundleEntryReaderInterface $reader)
    {
        $this->languageDataProvider = new LanguageDataProvider($path, $reader);

        parent::__construct($path, $reader);
    }

    /**
     * {@inheritdoc}
     */
    public function getLocales()
    {
        return Locale::getLocales();
    }

    /**
     * {@inheritdoc}
     */
    public function getLanguageName($lang, $region = null, $locale = null)
    {
        if (null === $locale) {
            $locale = \Locale::getDefault();
        }

        // Some languages are translated together with their region,
        // i.e. "en_GB" is translated as "British English"
        if (null !== $region) {
            try {
                return $this->languageDataProvider->getName($lang.'_'.$region, $locale);
            } catch (NoSuchEntryException $e) {
            }
        }

        return $this->languageDataProvider->getName($lang, $locale);
    }

    /**
     * {@inheritdoc}
     */
    public function getLanguageNames($locale = null)
    {
        if (null === $locale) {
            $locale = \Locale::getDefault();
        }

        return $this->languageDataProvider->getNames($locale);
    }

    /**
     * {@inheritdoc}
     */
    public function getScriptName($script, $lang = null, $locale = null)
    {
        if (null === $locale) {
            $locale = \Locale::getDefault();
        }

        return $this->readEntry($locale, array('Scripts', $script));
    }

    /**
     * {@inheritdoc}
     */
    public function getScriptNames($locale = null)
    {
        if (null === $locale) {
            $locale = \Locale::getDefault();
        }

        if (null === ($scripts = $this->readEntry($locale, array('Scripts')))) {
            return array();
        }

        if ($scripts instanceof \Traversable) {
            $scripts = iterator_to_array($scripts);
        }

        return $scripts;
    }
}
