<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\ChoiceList;

class MonthChoiceList extends PaddedChoiceList
{
    /**
     * Generates an array of localized month choices
     *
     * @param  array $months  The month numbers to generate
     * @return array          The localized months respecting the configured
     *                        locale and date format
     */
    public function __construct(\IntlDateFormatter $formatter, array $months, array $preferredChoices = array())
    {
        $pattern = $formatter->getPattern();

        if (preg_match('/M+/', $pattern, $matches)) {
            $formatter->setPattern($matches[0]);
            $choices = array();

            foreach ($months as $month) {
                $choices[$month] = $formatter->format(gmmktime(0, 0, 0, $month));
            }

            // I'd like to clone the formatter above, but then we get a
            // segmentation fault, so let's restore the old state instead
            $formatter->setPattern($pattern);

            DefaultChoiceList::__construct($choices, $preferredChoices);
        } else {
            parent::__construct($months, 2, '0', STR_PAD_LEFT, $preferredChoices);
        }

    }
}