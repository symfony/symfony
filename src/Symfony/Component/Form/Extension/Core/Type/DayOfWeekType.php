<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Core\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\Form\Extension\Core\ChoiceList\SimpleChoiceList;
use Symfony\Component\Form\Extension\Core\DataTransformer\DayOfWeekTransformer;

/**
 * Choice between all days of the week
 * Values are <1-7>, 1 = monday, 7 = sunday, ISO-8601
 * Labels are the localized names (Monday-Sunday)
 * The first day of the week is governed by the Locale
 *
 * @author Sebastien Lavoie <seb@wemakecustom.com>
 */
class DayOfWeekType extends AbstractType
{
    /**
     * IntlCalendar::DOW_* constants are not compatible with ISO-8601, redo mapping
     * @var int[]
     */
    private static $intlCalendarMapping = array(
        \IntlCalendar::DOW_MONDAY    => 1,
        \IntlCalendar::DOW_TUESDAY   => 2,
        \IntlCalendar::DOW_WEDNESDAY => 3,
        \IntlCalendar::DOW_THURSDAY  => 4,
        \IntlCalendar::DOW_FRIDAY    => 5,
        \IntlCalendar::DOW_SATURDAY  => 6,
        \IntlCalendar::DOW_SUNDAY    => 7,
    );

    const PATTERN_DOW = 'e';
    const PATTERN_SHORT = 'eee';
    const PATTERN_FULL = 'eeee';
    const PATTERN_LETTER = 'eeeee';

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $choiceList = function (Options $options) {
            static $cache = array();
            $cache_key = \Locale::getDefault().'|'.$options['label_format'];

            if (!isset($cache[$cache_key])) {
                $calendar = \IntlCalendar::createInstance();
                $days = DayOfWeekType::getOrderedDaysOfWeek($calendar->getFirstDayOfWeek());
                $transformer = new DayOfWeekTransformer($options['label_format']);
                $labels = array_map(array($transformer, 'transform'), $days);

                return $cache[$cache_key] = new SimpleChoiceList(array_combine($days, $labels));
            }

            return $cache[$cache_key];
        };

        $resolver->setDefaults(array(
            'label_format' => self::PATTERN_FULL,
            'choice_list' => $choiceList,
        ));

        $resolver->setAllowedValues(array(
            // http://userguide.icu-project.org/formatparse/datetime
            'label_format' => array('e', 'ee', 'eee', 'eeee', 'eeeee', 'eeeeee', 'E', 'EE', 'EEE', 'EEEE', 'EEEEE', 'EEEEEE'),
        ));
    }

    /**
     * Goes through all intlCalendarMapping once, starting at $firstDayOfWeek
     *
     * @internal Closure binding only available in 5.4+
     * @param int $firstDayOfWeek IntlCalendar::DOW_* constant
     * @return int[] array of iso-8601 integers
     */
    public static function getOrderedDaysOfWeek($firstDayOfWeek)
    {
        $days = array();

        // goes through all items once, starting at $firstDayOfWeek
        for ($index = $firstDayOfWeek - 1; $index < ($firstDayOfWeek + 6); $index++) {
            $dayOfWeek = ($index % 7) + 1;
            $iso = self::$intlCalendarMapping[$dayOfWeek];
            $days[] = $iso;
        }

        return $days;
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return 'choice';
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'dayofweek';
    }
}
