<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator;

use Symfony\Component\Translation\TranslatorInterface;

/**
 * Simple translator implementation that simply replaces the parameters in
 * the message IDs.
 *
 * Does not support translation domains or locales.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class DefaultTranslator implements TranslatorInterface
{
    /**
     * {@inheritdoc}
     */
    public function trans($id, array $parameters = array(), $domain = null, $locale = null)
    {
        return strtr($id, $parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function transChoice($id, $number, array $parameters = array(), $domain = null, $locale = null)
    {
        return strtr($id, $parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function setLocale($locale)
    {
        throw new \BadMethodCallException('Unsupported method.');
    }

    /**
     * {@inheritdoc}
     */
    public function getLocale()
    {
        throw new \BadMethodCallException('Unsupported method.');
    }
}
