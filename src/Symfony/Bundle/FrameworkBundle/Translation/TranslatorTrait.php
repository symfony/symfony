<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Translation;

use Symfony\Component\Translation\TranslatorInterface;

/**
 * Translator trait.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Michaël Garrez <michael.garrez@gmail.com>
 */
trait TranslatorTrait
{
    /**
     * @see TranslatorInterface::trans()
     */
    public function trans($id, array $parameters = array(), $domain = null, $locale = null)
    {
        return $this->container->get('translator')->trans($id, $parameters, $domain, $locale);
    }

    /**
     * @see TranslatorInterface::transChoice()
     */
    public function transChoice($id, $number, $parameters = array(), $domain = null, $locale = null)
    {
        return $this->container->get('translator')->transChoice($id, $number, $parameters, $domain, $locale);
    }
}
