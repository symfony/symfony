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

/**
 * IdentityTranslator does not translate anything.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @api
 *
 * @since v2.0.0
 */
class IdentityTranslator implements TranslatorInterface
{
    private $selector;
    private $locale;

    /**
     * Constructor.
     *
     * @param MessageSelector $selector The message selector for pluralization
     *
     * @api
     *
     * @since v2.0.0
     */
    public function __construct(MessageSelector $selector)
    {
        $this->selector = $selector;
    }

    /**
     * {@inheritdoc}
     *
     * @api
     *
     * @since v2.0.0
     */
    public function setLocale($locale)
    {
        $this->locale = $locale;
    }

    /**
     * {@inheritdoc}
     *
     * @api
     *
     * @since v2.0.0
     */
    public function getLocale()
    {
        return $this->locale ?: \Locale::getDefault();
    }

    /**
     * {@inheritdoc}
     *
     * @api
     *
     * @since v2.0.0
     */
    public function trans($id, array $parameters = array(), $domain = 'messages', $locale = null)
    {
        return strtr((string) $id, $parameters);
    }

    /**
     * {@inheritdoc}
     *
     * @api
     *
     * @since v2.0.0
     */
    public function transChoice($id, $number, array $parameters = array(), $domain = 'messages', $locale = null)
    {
        return strtr($this->selector->choose((string) $id, (int) $number, $locale ?: $this->getLocale()), $parameters);
    }
}
