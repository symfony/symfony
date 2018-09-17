<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Formatter;

/**
 * A Polyfill for IntlMessageFormatter for users that do not have the icu extension installed.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class IntlSimpleMessageFormatter implements MessageFormatterInterface
{
    /**
     * {@inheritdoc}
     */
    public function format($message, $locale, array $parameters = array())
    {
        $message = $this->handlePlural($message, $parameters);
        $message = $this->handleSelect($message, $parameters);
        $message = $this->replaceParameters($message, $parameters);

        return $message;
    }

    private function handlePlural(string $message, array $parameters): string
    {
        $lookupMap = array(0 => 'zero', 1 => 'one', 2 => 'two', 3 => 'few');

        foreach ($parameters as $key => $value) {
            $regex = '|{ ?'.$key.', plural,(.*)|sm';
            if (preg_match($regex, $message, $match)) {
                $blockContent = $this->findBlock($match[1]);
                $fullBlock = substr($match[0], 0, strpos($match[0], $blockContent) + \strlen($blockContent) + 1);

                $lookup = array();
                if (\is_int($value)) {
                    $lookup[] = '='.$value;
                }
                if (isset($lookupMap[$value])) {
                    $lookup[] = $lookupMap[$value];
                } elseif ($value > 3) {
                    $lookup[] = 'many';
                }
                $lookup[] = 'other';

                foreach ($lookup as $l) {
                    if (preg_match('|'.$l.' ?{(.*)|sm', $blockContent, $blockMatch)) {
                        $result = $this->findBlock($blockMatch[1]);
                        $blockReplacement = str_replace('#', $value, $result);

                        return str_replace($fullBlock, $blockReplacement, $message);
                    }
                }
            }
        }

        return $message;
    }

    private function handleSelect(string $message, array $parameters): string
    {
        $regex = '|{ ?([a-zA-Z]+), select,(.*)|sm';
        if (preg_match($regex, $message, $match)) {
            $blockContent = $this->findBlock($match[2]);
            $fullBlock = substr($match[0], 0, strpos($match[0], $blockContent) + \strlen($blockContent) + 1);
            foreach ($parameters as $key => $value) {
                if ($match[1] === $key) {
                    if (preg_match('|'.$value.' ?{(.*)|sm', $blockContent, $blockMatch)) {
                        $result = $this->findBlock($blockMatch[1]);

                        return str_replace($fullBlock, $result, $message);
                    }
                }
            }

            // If no match
            if (preg_match('|other ?{(.*)|sm', $blockContent, $blockMatch)) {
                $result = $this->findBlock($blockMatch[1]);

                return str_replace($fullBlock, $result, $message);
            }
        }

        return $message;
    }

    private function replaceParameters(string $message, array $parameters): string
    {
        $updatedParameters = array();
        foreach ($parameters as $key => $value) {
            $updatedParameters[sprintf('{%s}', $key)] = $value;
        }

        return strtr($message, $updatedParameters);
    }

    private function findBlock(string $input): string
    {
        // How may open curly brackets ({) do we got?
        $open = 1;
        $block = '';
        for ($i = 0; $i < \strlen($input); ++$i) {
            if ('{' === $input[$i]) {
                ++$open;
            } elseif ('}' === $input[$i]) {
                --$open;
            }
            if (0 === $open) {
                $block = substr($input, 0, $i);
                break;
            }
        }

        return $block;
    }
}
