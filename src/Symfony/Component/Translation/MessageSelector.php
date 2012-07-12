<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation;

/**
 * MessageSelector.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @api
 */
class MessageSelector
{
    /**
     * Given a message with different plural translations separated by a
     * pipe (|), this method returns the correct portion of the message based
     * on the given number, locale and the pluralization rules in the message
     * itself.
     *
     * The message supports two different types of pluralization rules:
     *
     * interval: {0} There are no apples|{1} There is one apple|]1,Inf] There are %count% apples
     * indexed:  There is one apple|There are %count% apples
     *
     * The indexed solution can also contain labels (e.g. one: There is one apple).
     * This is purely for making the translations more clear - it does not
     * affect the functionality.
     *
     * The two methods can also be mixed:
     *     {0} There are no apples|one: There is one apple|more: There are %count% apples
     *
     * @param string  $message The message being translated
     * @param integer $number  The number of items represented for the message
     * @param string  $locale  The locale to use for choosing
     *
     * @return string
     *
     * @throws InvalidArgumentException
     *
     * @api
     */
    public function choose($message, $number, $locale)
    {
        $parts = explode('|', $message);
        $explicitRules = array();
        $standardRules = array();
        foreach ($parts as $part) {
            $part = trim($part);

            if (preg_match('/^(?P<interval>'.Interval::getIntervalRegexp().')\s*(?P<message>.*?)$/x', $part, $matches)) {
                $explicitRules[$matches['interval']] = $matches['message'];
            } elseif (preg_match('/^\w+\:\s*(.*?)$/', $part, $matches)) {
                $standardRules[] = $matches[1];
            } else {
                $standardRules[] = $part;
            }
        }

        // try to match an explicit rule, then fallback to the standard ones
        foreach ($explicitRules as $interval => $m) {
            if (Interval::test($number, $interval)) {
                return $m;
            }
        }

        $position = PluralizationRules::get($number, $locale);
        if (!isset($standardRules[$position])) {
            throw new \InvalidArgumentException(sprintf('Unable to choose a translation for "%s" with locale "%s". Double check that this translation has the correct plural options (e.g. "There is one apple|There are %%count%% apples").', $message, $locale));
        }

        return $standardRules[$position];
    }

    /**
     * Given a message with different plural translations separated by a
     * pipe (|), this method returns the correct portion of the message based
     * on the given variables, locale and the pluralization rules in the message
     * itself.
     *
     * This is the syntax of the messages:
     * %variable% [gender][plural_position]: String
     *
     * Where
     *    %variable% is any kind of string (all characters except spaces and |),
     *    [gender]          is one of the following: m n f (male, female, neuter)
     *    [plural_position] is an integer that follows the same convetions of the pluralization rules
     *                      that you can find in Symfony\Component\Translation\PluralizationRules
     *    String            is any kind of string (except the | sign)
     *
     * You can specify (and it's highly reccomended) a generic translation as the first position (without using any specific syntax).
     * This will be returned in case no other translation matches the given parameters.
     *
     * Some examples follow:
     *     %user% wrote on %friend%'s wall for his/her birthday | %friend% f: %user% wrote on %friend%'s wall for her birthday | %friend% m: %user% wrote on %friend%'s wall for his birthday
     *     You are a very good boy! | %user% m1: You are very good boys! | %user% f0: You are a very good girl! | %user% 1f: You are very good girls!
     *
     * @param string $message    The message being translated
     * @param array  $parameters The variables on which the message depends.
     * @param string $locale     The locale to use for choosing
     *
     * @return string
     *
     * @throws InvalidArgumentException
     *
     * @api
     */
    public function chooseByParams($message, $parameters, $locale)
    {
        $possibleMessages = explode('|', $message);
        $bestMessage = null;
        $bestMessageIndex = 0;

        foreach ($possibleMessages as $possibleMessage) {
            $possibleMessage = trim($possibleMessage);
            // if this rule is not matched, we have a generic translation
            if (preg_match('/^(.+)\:\s*(.*?)$/', $possibleMessage, $matches)) {
                $parts = explode(',', $matches[1]);
                $possibleMessage = $matches[2];
                $isValid = true;
                $priority = 0;

                foreach ($parts as $part) {
                    $part = trim($part);
                    // checks if the message can fit with the variables
                    if (!preg_match('/^(?P<variable>[^\s]+)\s*((?P<number>\d)(\s*(?P<gender>[m|n|f]))?|(?P<gender1>[m|n|f])(\s*(?P<number1>\d))?)$/', $part, $matches)) {
                        throw new \InvalidArgumentException(sprintf('Syntax error on translation of "%s" with locale "%s".', $message, $locale));
                    }

                    $variable = $matches['variable'] ?: null;
                    $gender = isset($matches['gender']) && '' !== $matches['gender'] ? $matches['gender'] : (isset($matches['gender1']) && '' !== $matches['gender1'] ? $matches['gender1'] : null);
                    $number = isset($matches['number']) && '' !== $matches['number'] ? $matches['number'] : (isset($matches['number1']) && '' !== $matches['number1'] ? $matches['number1'] : null);

                    // if the variable is not passed to the method this string is not usable
                    if (!isset($parameters[$variable])) {
                        $isValid = false;
                        break;
                    }

                    // give 1 point if the variable matches gender/number, 2 points if both. Exclude it if doesn't match
                    if (isset($parameters[$variable]['gender'])) {
                        if ($gender == $parameters[$variable]['gender']) {
                            ++$priority;
                        } else {
                            $isValid = false;
                            break;
                        }
                    }
                    if (isset($parameters[$variable]['number'])) {
                        if (PluralizationRules::get($parameters[$variable]['number'], $locale) == $number) {
                            ++$priority;
                        } else {
                            $isValid = false;
                            break;
                        }
                    }
                }

                // We now have a priority. We set this as the best message if it's more relevant than the previous.
                if (true === $isValid && $bestMessageIndex < $priority) {
                    $bestMessage = $possibleMessage;
                    $bestMessageIndex = $priority;
                }
            } else {
                if (null === $bestMessage) {
                    $bestMessage = $possibleMessage;
                }
            }
        }
        if (null === $bestMessage) {
            throw new \InvalidArgumentException(sprintf('Unable to choose a translation for "%s" with locale "%s". Please specify a default translation.', $message, $locale));
        }

        return $bestMessage;
    }
}
