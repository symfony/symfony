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
use Symfony\Component\Translation\Formatter\DefaultMessageFormatter;

/**
 * IdentityTranslator does not translate anything.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @api
 */
class IdentityTranslator implements TranslatorInterface
{
    private $selector;
    private $formatter;
    private $locale;

    /**
     * @param MessageFormatterInterface|MessageSelector $formatter The message formatter
     *
     * @api
     */
    public function __construct($formatter = null)
    {
        if ($formatter instanceof MessageSelector) {
            @trigger_error('Passing a MessageSelector instance into the '.__METHOD__.' is deprecated since version 2.8 and will be removed in 3.0. Inject a MessageFormatterInterface instance instead.', E_USER_DEPRECATED);
            $this->selector = $formatter;
            $formatter = new DefaultMessageFormatter();
        } else {
            $this->selector = new MessageSelector();
        }

        $this->formatter = $formatter ?: new DefaultMessageFormatter();
        if (!$this->formatter instanceof MessageFormatterInterface) {
            throw new \InvalidArgumentException(sprintf('The message formatter "%s" must implement MessageFormatterInterface.', get_class($this->formatter)));
        }
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

        return $this->formatter->format($locale, (string) $id, $parameters);
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

        return $this->formatter->format($locale, $this->selector->choose((string) $id, (int) $number, $locale ?: $this->getLocale()), $parameters);
    }
}
