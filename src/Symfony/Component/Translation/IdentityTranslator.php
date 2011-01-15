<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation;

/**
 * IdentityTranslator does not translate anything.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class IdentityTranslator implements TranslatorInterface
{
    protected $selector;

    /**
     * Constructor.
     *
     * @param MessageSelector $selector The message selector for pluralization
     */
    public function __construct(MessageSelector $selector)
    {
        $this->selector = $selector;
    }

    /**
     * {@inheritdoc}
     */
    public function setLocale($locale)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getLocale()
    {
    }

    /**
     * {@inheritdoc}
     */
    public function trans($id, array $parameters = array(), $domain = 'messages', $locale = null)
    {
        return strtr($id, $parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function transChoice($id, $number, array $parameters = array(), $domain = 'messages', $locale = null)
    {
        return strtr($this->selector->choose($id, (int) $number, $locale), $parameters);
    }
}
