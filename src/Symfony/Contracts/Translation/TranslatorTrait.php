<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Contracts\Translation;

/**
 * A trait to help implement TranslatorInterface.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
trait TranslatorTrait
{
    private $locale;

    /**
     * {@inheritdoc}
     */
    public function setLocale($locale)
    {
        $this->locale = (string) $locale;
    }

    /**
     * {@inheritdoc}
     */
    public function getLocale()
    {
        return $this->locale ?: \Locale::getDefault();
    }

    /**
     * {@inheritdoc}
     */
    public function trans($id, array $parameters = array(), $domain = null, $locale = null)
    {
        return strtr((string) $id, $parameters);
    }
}
