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

use Symfony\Component\Translation\Formatter\MessageFormatterInterface;

/**
 * IdentityTranslator does not translate anything.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @api
 */
class IdentityTranslator implements TranslatorInterface
{
    private $formatter;
    private $locale;

    /**
     * Constructor.
     *
     * @param MessageFormatterInterface $formatter The message formatter
     *
     * @api
     */
    public function __construct(MessageFormatterInterface $formatter)
    {
        $this->formatter = $formatter;
    }

    /**
     * {@inheritdoc}
     *
     * @api
     */
    public function setLocale($locale)
    {
        $this->locale = $locale;
    }

    /**
     * {@inheritdoc}
     *
     * @api
     */
    public function getLocale()
    {
        return $this->locale ?: \Locale::getDefault();
    }

    /**
     * {@inheritdoc}
     *
     * @api
     */
    public function trans($id, array $parameters = array(), $domain = null, $locale = null)
    {
        if (!$locale) {
            $locale = $this->getLocale();
        }

        return $this->formatter->format($locale, (string) $id, null, $parameters);
    }

    /**
     * {@inheritdoc}
     *
     * @api
     */
    public function transChoice($id, $number, array $parameters = array(), $domain = null, $locale = null)
    {
        if (!$locale) {
            $locale = $this->getLocale();
        }

        return $this->formatter->format($locale, (string) $id, (integer) $number, $parameters);
    }
}
