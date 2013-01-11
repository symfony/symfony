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
     * Generates an array of localized month choices.
     *
     * @param IntlDateFormatter $formatter An IntlDateFormatter instance
     * @param array             $months    The month numbers to generate
     */
    public function __construct(\IntlDateFormatter $formatter, array $months)
    {
        parent::__construct(array_combine($months, $months), 2, '0', STR_PAD_LEFT);
        $this->formatter = $formatter;
    }

    /**
     * Initializes the list of months.
     *
     * @throws UnexpectedTypeException if the function does not return an array
     */
    protected function load()
    {
        parent::load();

        $pattern = $this->formatter->getPattern();
        $timezone = $this->formatter->getTimezoneId();

        if (version_compare(phpversion(), '5.5.0-dev', '<')) {
            $this->formatter->setTimezoneId('UTC');
        } else {
            $this->formatter->setTimezone('UTC');
        }

        if (preg_match('/M+/', $pattern, $matches)) {
            $this->formatter->setPattern($matches[0]);

            foreach ($this->choices as $choice => $value) {
                $this->choices[$choice] = $this->formatter->format(gmmktime(0, 0, 0, $value, 15));
            }

            // I'd like to clone the formatter above, but then we get a
            // segmentation fault, so let's restore the old state instead
            $this->formatter->setPattern($pattern);
        }

        if (version_compare(phpversion(), '5.5.0-dev', '<')) {
            $this->formatter->setTimezoneId($timezone);
        } else {
            $this->formatter->setTimezone($timezone);
        }
    }
}
