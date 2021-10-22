<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Completion\Output;

use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
class BashCompletionOutput implements CompletionOutputInterface
{
    public function write(CompletionSuggestions $suggestions, OutputInterface $output): void
    {
        $options = [];
        foreach ($suggestions->getOptionSuggestions() as $option) {
            $options[] = '--'.$option->getName();
        }
        $output->write(implode(' ', $options));

        $output->writeln($this->normalizeSuggestions($suggestions->getValueSuggestions()));
    }

    /**
     * Escapes special chars (e.g. backslash or space) and puts quotes around
     * the suggestions whenever escaping was needed.
     */
    private function normalizeSuggestions(array $suggestions): string
    {
        $includesUnsafeChars = false;
        $suggestions = array_map(function ($value) use (&$includesUnsafeChars) {
            $newValue = str_replace('\\', '\\\\', $value);
            $newValue = str_replace(' ', '\ ', $value);
            $includesUnsafeChars = $includesUnsafeChars || $newValue !== $value;

            return $newValue;
        }, $suggestions);

        return $includesUnsafeChars ? "\\'".implode("\\' \\'", $suggestions)."\\'" : implode(' ', $suggestions);
    }
}
