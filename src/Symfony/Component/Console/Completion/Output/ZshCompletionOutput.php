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
 * @author Jitendra A <adhocore@gmail.com>
 */
class ZshCompletionOutput implements CompletionOutputInterface
{
    public function write(CompletionSuggestions $suggestions, OutputInterface $output): void
    {
        $options = $suggestions->getValueSuggestions();
        foreach ($suggestions->getOptionSuggestions() as $option) {
            $options[] = '--'.$option->getName()."\t".$option->getDescription();
        }
        $output->writeln(implode("\n", $options));
    }
}
