<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form;

use Symfony\Component\Locale\Locale;

/**
 * A field for selecting from a list of countries.
 *
 * In addition to the ChoiceField options, this field has the following
 * options:
 *
 *  * empty_value:  If set to a non-false value, an "empty" option will
 *                  be added to the top of the countries choices. A
 *                  common value might be "Choose a country". Default: false.
 *
 * @see Symfony\Component\Form\ChoiceField
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
class CountryField extends ChoiceField
{
    protected function configure()
    {
        $this->addOption('empty_value', false);

        $choices = Locale::getDisplayCountries($this->locale);

        if (false !== $this->getOption('empty_value')) {
            $choices = array('' => $this->getOption('empty_value')) + $choices;
        }

        $this->addOption('choices', $choices);

        parent::configure();
    }
}