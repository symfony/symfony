<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Helper;

use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Symfony Style Guide compliant question helper.
 *
 * @author Kevin Bond <kevinbond@gmail.com>
 */
class SymfonyAskQuestion extends AskQuestion
{
    /**
     * {@inheritdoc}
     */
    protected function writePrompt()
    {
        $text = OutputFormatter::escapeTrailingBackslash($this->question->getQuestion());
        $default = $this->question->getDefault();

        switch (true) {
            case null === $default:
                $text = sprintf(' <info>%s</info>:', $text);

                break;

            case $this->question instanceof ConfirmationQuestion:
                $text = sprintf(' <info>%s (yes/no)</info> [<comment>%s</comment>]:', $text, $default ? 'yes' : 'no');

                break;

            case $this->question instanceof ChoiceQuestion && $this->question->isMultiselect():
                $choices = $this->question->getChoices();
                $default = explode(',', $default);

                foreach ($default as $key => $value) {
                    $default[$key] = $choices[trim($value)];
                }

                $text = sprintf(' <info>%s</info> [<comment>%s</comment>]:', $text, OutputFormatter::escape(implode(', ', $default)));

                break;

            case $this->question instanceof ChoiceQuestion:
                $choices = $this->question->getChoices();
                $text = sprintf(' <info>%s</info> [<comment>%s</comment>]:', $text, OutputFormatter::escape(isset($choices[$default]) ? $choices[$default] : $default));

                break;

            default:
                $text = sprintf(' <info>%s</info> [<comment>%s</comment>]:', $text, OutputFormatter::escape($default));
        }

        $this->output->writeln($text);

        if ($this->question instanceof ChoiceQuestion) {
            $width = max(array_map('strlen', array_keys($this->question->getChoices())));

            foreach ($this->question->getChoices() as $key => $value) {
                $this->output->writeln(sprintf("  [<comment>%-${width}s</comment>] %s", $key, $value));
            }
        }

        $this->output->write(' > ');
    }

    /**
     * {@inheritdoc}
     */
    protected function writeError(\Exception $error)
    {
        if ($this->output instanceof SymfonyStyle) {
            $this->output->newLine();
            $this->output->error($error->getMessage());

            return;
        }

        parent::writeError($error);
    }
}
