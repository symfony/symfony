<?php

namespace Symfony\Component\Translation;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

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
