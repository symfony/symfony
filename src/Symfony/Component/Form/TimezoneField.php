<?php

namespace Symfony\Component\Form;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

class TimezoneField extends ChoiceField
{
    /**
     * Stores the available timezone choices
     * @var array
     */
    protected static $timezones = array();

    /**
     * {@inheritDoc}
     */
    public function configure()
    {
        $this->addOption('choices', self::getTimezoneChoices());

        parent::configure();
    }

    /**
     * Preselects the server timezone if the field is empty and required
     *
     * {@inheritDoc}
     */
    public function getDisplayedData()
    {
        $data = parent::getDisplayedData();

        if ($data == null && $this->isRequired()) {
            $data = date_default_timezone_get();
        }

        return $data;
    }

    /**
     * Returns the timezone choices
     *
     * The choices are generated from the ICU function
     * \DateTimeZone::listIdentifiers(). They are cached during a single request,
     * so multiple timezone fields on the same page don't lead to unnecessary
     * overhead.
     *
     * @return array  The timezone choices
     */
    protected static function getTimezoneChoices()
    {
        if (count(self::$timezones) == 0) {
            foreach (\DateTimeZone::listIdentifiers() as $timezone) {
                $parts = explode('/', $timezone);

                if (count($parts) > 2) {
                    $region = $parts[0];
                    $name = $parts[1].' - '.$parts[2];
                } else if (count($parts) > 1) {
                    $region = $parts[0];
                    $name = $parts[1];
                } else {
                    $region = 'Other';
                    $name = $parts[0];
                }

                if (!isset(self::$timezones[$region])) {
                    self::$timezones[$region] = array();
                }

                self::$timezones[$region][$timezone] = str_replace('_', ' ', $name);
            }
        }

        return self::$timezones;
    }
}
