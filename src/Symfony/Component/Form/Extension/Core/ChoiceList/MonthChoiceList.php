<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Core\ChoiceList;

class MonthChoiceList extends PaddedChoiceList
{
    private $formatter;

    /**
     * Generates an array of localized month choices
     *
     * @param IntlDateFormatter $formatter An IntlDateFormatter instance
     * @param array             $months    The month numbers to generate
     */
    public function __construct(\IntlDateFormatter $formatter, array $months)
    {
        parent::__construct($months, 2, '0', STR_PAD_LEFT);

        $this->formatter = $formatter;
    }

    protected function load()
    {
        parent::load();

        $pattern = $this->formatter->getPattern();
        $timezone = $this->formatter->getTimezoneId();

        $this->formatter->setTimezoneId(\DateTimeZone::UTC);

        if (preg_match('/M+/', $pattern, $matches)) {
            $this->formatter->setPattern($matches[0]);

            foreach ($this->choices as $choice => $value) {
                // It's important to specify the first day of the month here!
                $this->choices[$choice] = $this->formatter->format(gmmktime(0, 0, 0, $choice, 1));
            }

            // I'd like to clone the formatter above, but then we get a
            // segmentation fault, so let's restore the old state instead
            $this->formatter->setPattern($pattern);
        }

        $this->formatter->setTimezoneId($timezone);
    }
}