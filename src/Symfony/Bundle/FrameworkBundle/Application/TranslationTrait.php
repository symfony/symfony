<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Application;

/**
 * Translation trait.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Michaël Garrez <michael.garrez@gmail.com>
 */
trait TranslationTrait
{
    /**
     * Translates the given message.
     *
     * @param string      $id         The message id (may also be an object that can be cast to string)
     * @param array       $parameters An array of parameters for the message
     * @param string|null $domain     The domain for the message or null to use the default
     * @param string|null $locale     The locale or null to use the default
     *
     * @throws \InvalidArgumentException If the locale contains invalid characters
     *
     * @return string The translated string
     */
    public function trans($id, array $parameters = array(), $domain = null, $locale = null)
    {
        if (!$this->container->has('translator')) {
            throw new \LogicException('You can not use the trans method if translator is disabled.');
        }

        return $this->container->get('translator')->trans($id, $parameters, $domain, $locale);
    }

    /**
     * Translates the given choice message by choosing a translation according to a number.
     *
     * @param string      $id         The message id (may also be an object that can be cast to string)
     * @param int         $number     The number to use to find the indice of the message
     * @param array       $parameters An array of parameters for the message
     * @param string|null $domain     The domain for the message or null to use the default
     * @param string|null $locale     The locale or null to use the default
     *
     * @throws \InvalidArgumentException If the locale contains invalid characters
     *
     * @return string The translated string
     */
    public function transChoice($id, $number, $parameters = array(), $domain = null, $locale = null)
    {
        if (!$this->container->has('translator')) {
            throw new \LogicException('You can not use the transChoice method if translator is disabled.');
        }

        return $this->container->get('translator')->transChoice($id, $number, $parameters, $domain, $locale);
    }
}
