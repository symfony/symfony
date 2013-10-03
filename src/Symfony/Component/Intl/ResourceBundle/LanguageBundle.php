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

use Symfony\Component\Intl\Exception\NoSuchEntryException;

/**
 * Default implementation of {@link LanguageBundleInterface}.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class LanguageBundle extends AbstractBundle implements LanguageBundleInterface
{
    /**
     * {@inheritdoc}
     */
    public function getLocales()
    {
        $locales = $this->readEntry('misc', array('Locales'));

        if ($locales instanceof \Traversable) {
            $locales = iterator_to_array($locales);
        }

        return $locales;
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
                return $this->readEntry($locale, array('Languages', $lang.'_'.$region));
            } catch (NoSuchEntryException $e) {
            }
        }

        return $this->readEntry($locale, array('Languages', $lang));
    }

    /**
     * {@inheritdoc}
     */
    public function getLanguageNames($locale = null)
    {
        if (null === $locale) {
            $locale = \Locale::getDefault();
        }

        if (null === ($languages = $this->readEntry($locale, array('Languages')))) {
            return array();
        }

        if ($languages instanceof \Traversable) {
            $languages = iterator_to_array($languages);
        }

        return $languages;
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
