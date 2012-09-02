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

use Symfony\Component\Console\Output\OutputInterface;

/**
 * The Dialog class provides helpers to interact with the user.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class DialogHelper extends Helper
{
    private $inputStream;

    /**
     * Asks to select array value to the user.
     *
     * @param OutputInterface $output   An Output instance
     * @param string|array    $question The question to ask
     * @param array           $choices  List of choices to pick from
     * @param Boolean         $default  The default answer if the user enters nothing
     * @param array           $options  Display options:
     *                                      'attempts' - Max number of times to ask before giving up (false by default,
     *                                                  which means infinite)
     *                                      'error_template' - Message which will be shown if invalid value from choice
     *                                                  list would be picked. Defaults to "Value '%s' is not in provided
     *                                                  in choice list"
     *                                      'return' - What should be returned from choice list - 'key' or 'value'
     *
     * @return mixed
     */
    public function select(OutputInterface $output, $question, $choices, $default = null, array $options = array())
    {
        $options = array_merge(array(
            'attempts' => false,
            'error_template' => "Value '%s' is not in provided in choice list",
            'return' => 'key'
        ), $options);

        $width = 0;
        foreach (array_keys($choices) as $key) {
            $width = strlen($key) > $width ? strlen($key) : $width;
        }
        $width += 2;

        $messages = array();
        $messages[] = "<comment>$question</comment>";
        foreach($choices as $key => $value) {
            $messages[] = sprintf("  <info>%-${width}s</info> %s", $key, $value);
        }

        $messages = join(PHP_EOL, $messages);
        $output->writeln($messages);

        $result = $this->askAndValidate($output, '> ', function($picked) use($choices, $options)
        {
            if (empty($choices[$picked])) {
                throw new \InvalidArgumentException(sprintf($options['error_template'], $picked));
            }
            return $picked;
        }, $options['attempts'], $default);

        switch($options['return']) {
            case 'key': return $result;
            case 'value': return $choices[$result];
        }
    }

    /**
     * Asks a question to the user.
     *
     * @param OutputInterface $output   An Output instance
     * @param string|array    $question The question to ask
     * @param string          $default  The default answer if none is given by the user
     *
     * @return string The user answer
     *
     * @throws \RuntimeException If there is no data to read in the input stream
     */
    public function ask(OutputInterface $output, $question, $default = null)
    {
        $output->write($question);

        $ret = fgets($this->inputStream ?: STDIN, 4096);
        if (false === $ret) {
            throw new \RuntimeException('Aborted');
        }
        $ret = trim($ret);

        return strlen($ret) > 0 ? $ret : $default;
    }

    /**
     * Asks a confirmation to the user.
     *
     * The question will be asked until the user answers by nothing, yes, or no.
     *
     * @param OutputInterface $output   An Output instance
     * @param string|array    $question The question to ask
     * @param Boolean         $default  The default answer if the user enters nothing
     *
     * @return Boolean true if the user has confirmed, false otherwise
     */
    public function askConfirmation(OutputInterface $output, $question, $default = true)
    {
        $answer = 'z';
        while ($answer && !in_array(strtolower($answer[0]), array('y', 'n'))) {
            $answer = $this->ask($output, $question);
        }

        if (false === $default) {
            return $answer && 'y' == strtolower($answer[0]);
        }

        return !$answer || 'y' == strtolower($answer[0]);
    }

    /**
     * Asks for a value and validates the response.
     *
     * The validator receives the data to validate. It must return the
     * validated data when the data is valid and throw an exception
     * otherwise.
     *
     * @param OutputInterface $output    An Output instance
     * @param string|array    $question  The question to ask
     * @param callback        $validator A PHP callback
     * @param integer         $attempts  Max number of times to ask before giving up (false by default, which means infinite)
     * @param string          $default   The default answer if none is given by the user
     *
     * @return mixed
     *
     * @throws \Exception When any of the validators return an error
     */
    public function askAndValidate(OutputInterface $output, $question, $validator, $attempts = false, $default = null)
    {
        $error = null;
        while (false === $attempts || $attempts--) {
            if (null !== $error) {
                $output->writeln($this->getHelperSet()->get('formatter')->formatBlock($error->getMessage(), 'error'));
            }

            $value = $this->ask($output, $question, $default);

            try {
                return call_user_func($validator, $value);
            } catch (\Exception $error) {
            }
        }

        throw $error;
    }

    /**
     * Sets the input stream to read from when interacting with the user.
     *
     * This is mainly useful for testing purpose.
     *
     * @param resource $stream The input stream
     */
    public function setInputStream($stream)
    {
        $this->inputStream = $stream;
    }

    /**
     * Returns the helper's input stream
     *
     * @return string
     */
    public function getInputStream()
    {
        return $this->inputStream;
    }

    /**
     * Returns the helper's canonical name.
     *
     * @return string The helper name
     */
    public function getName()
    {
        return 'dialog';
    }
}
