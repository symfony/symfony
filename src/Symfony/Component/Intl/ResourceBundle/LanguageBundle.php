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

/**
 * Default implementation of {@link LanguageBundleInterface}.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @internal
 */
class LanguageBundle extends AbstractBundle implements LanguageBundleInterface
{
    /**
     * {@inheritdoc}
     */
    public function getLanguageName($lang, $region = null, $locale = null)
    {
        if (null === $locale) {
            $locale = \Locale::getDefault();
        }

        if (null === ($languages = $this->readEntry($locale, array('Languages'), true))) {
            return;
        }

        // Some languages are translated together with their region,
        // i.e. "en_GB" is translated as "British English"
        if (null !== $region && isset($languages[$lang.'_'.$region])) {
            return $languages[$lang.'_'.$region];
        }

        return $languages[$lang];
    }

    /**
     * {@inheritdoc}
     */
    public function getLanguageNames($locale = null)
    {
        if (null === $locale) {
            $locale = \Locale::getDefault();
        }

        if (null === ($languages = $this->readEntry($locale, array('Languages'), true))) {
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

        $data = $this->read($locale);

        // Some languages are translated together with their script,
        // e.g. "zh_Hans" is translated as "Simplified Chinese"
        if (null !== $lang && isset($data['Languages'][$lang.'_'.$script])) {
            $langName = $data['Languages'][$lang.'_'.$script];

            // If the script is appended in braces, extract it, e.g. "zh_Hans"
            // is translated as "Chinesisch (vereinfacht)" in locale "de"
            if (strpos($langName, '(') !== false) {
                list($langName, $scriptName) = preg_split('/[\s()]/', $langName, null, PREG_SPLIT_NO_EMPTY);

                return $scriptName;
            }
        }

        // "af" (Afrikaans) has no "Scripts" block
        if (!isset($data['Scripts'][$script])) {
            return;
        }

        return $data['Scripts'][$script];
    }

    /**
     * {@inheritdoc}
     */
    public function getScriptNames($locale = null)
    {
        if (null === $locale) {
            $locale = \Locale::getDefault();
        }

        if (null === ($scripts = $this->readEntry($locale, array('Scripts'), true))) {
            return array();
        }

        if ($scripts instanceof \Traversable) {
            $scripts = iterator_to_array($scripts);
        }

        return $scripts;
    }
}
