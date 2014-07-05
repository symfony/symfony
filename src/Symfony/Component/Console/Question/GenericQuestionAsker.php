<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Question;

use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * This asker can ask any type of question.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class GenericQuestionAsker implements QuestionAskerInterface
{
    private static $shell;
    private $question;
    private $sttyAvailable;

    /**
     * Creates a new generic question asker.
     *
     * @param Question $question      The question that will be asked
     * @param bool     $sttyAvailable A flag to tell if stty is available
     */
    public function __construct(Question $question, $sttyAvailable)
    {
        $this->question = $question;
        $this->sttyAvailable = $sttyAvailable;
    }

    /**
     * {@inheritdoc}
     */
    public function ask(OutputInterface $output, $inputStream)
    {
        $message = $this->question->getQuestion();
        if ($this->question instanceof ChoiceQuestion) {
            $width = max(array_map('strlen', array_keys($this->question->getChoices())));

            $messages = (array) $this->question->getQuestion();
            foreach ($this->question->getChoices() as $key => $value) {
                $messages[] = sprintf("  [<info>%-${width}s</info>] %s", $key, $value);
            }

            $output->writeln($messages);

            $message = $this->question->getPrompt();
        }

        $output->write($message);

        $autocomplete = $this->question->getAutocompleterValues();
        if (null === $autocomplete || !$this->sttyAvailable) {
            $ret = false;
            if ($this->question->isHidden()) {
                try {
                    $ret = trim($this->getHiddenResponse($output, $inputStream));
                } catch (\RuntimeException $e) {
                    if (!$this->question->isHiddenFallback()) {
                        throw $e;
                    }
                }
            }

            if (false === $ret) {
                $ret = fgets($inputStream, 4096);
                if (false === $ret) {
                    throw new \RuntimeException('Aborted');
                }
                $ret = trim($ret);
            }
        } else {
            $ret = trim($this->autocomplete($output, $inputStream));
        }

        return $ret;
    }

    /**
     * Autocompletes a question.
     *
     * @param OutputInterface $output
     * @param resource        $inputStream
     *
     * @return string
     */
    private function autocomplete(OutputInterface $output, $inputStream)
    {
        $autocomplete = $this->question->getAutocompleterValues();
        $ret = '';

        $i = 0;
        $ofs = -1;
        $matches = $autocomplete;
        $numMatches = count($matches);

        $sttyMode = shell_exec('stty -g');

        // Disable icanon (so we can fread each keypress) and echo (we'll do echoing here instead)
        shell_exec('stty -icanon -echo');

        // Add highlighted text style
        $output->getFormatter()->setStyle('hl', new OutputFormatterStyle('black', 'white'));

        // Read a keypress
        while (!feof($inputStream)) {
            $c = fread($inputStream, 1);

            // Backspace Character
            if ("\177" === $c) {
                if (0 === $numMatches && 0 !== $i) {
                    $i--;
                    // Move cursor backwards
                    $output->write("\033[1D");
                }

                if ($i === 0) {
                    $ofs = -1;
                    $matches = $autocomplete;
                    $numMatches = count($matches);
                } else {
                    $numMatches = 0;
                }

                // Pop the last character off the end of our string
                $ret = substr($ret, 0, $i);
            } elseif ("\033" === $c) { // Did we read an escape sequence?
                $c .= fread($inputStream, 2);

                // A = Up Arrow. B = Down Arrow
                if (isset($c[2]) && ('A' === $c[2] || 'B' === $c[2])) {
                    if ('A' === $c[2] && -1 === $ofs) {
                        $ofs = 0;
                    }

                    if (0 === $numMatches) {
                        continue;
                    }

                    $ofs += ('A' === $c[2]) ? -1 : 1;
                    $ofs = ($numMatches + $ofs) % $numMatches;
                }
            } elseif (ord($c) < 32) {
                if ("\t" === $c || "\n" === $c) {
                    if ($numMatches > 0 && -1 !== $ofs) {
                        $ret = $matches[$ofs];
                        // Echo out remaining chars for current match
                        $output->write(substr($ret, $i));
                        $i = strlen($ret);
                    }

                    if ("\n" === $c) {
                        $output->write($c);
                        break;
                    }

                    $numMatches = 0;
                }

                continue;
            } else {
                $output->write($c);
                $ret .= $c;
                $i++;

                $numMatches = 0;
                $ofs = 0;

                foreach ($autocomplete as $value) {
                    // If typed characters match the beginning chunk of value (e.g. [AcmeDe]moBundle)
                    if (0 === strpos($value, $ret) && $i !== strlen($value)) {
                        $matches[$numMatches++] = $value;
                    }
                }
            }

            // Erase characters from cursor to end of line
            $output->write("\033[K");

            if ($numMatches > 0 && -1 !== $ofs) {
                // Save cursor position
                $output->write("\0337");
                // Write highlighted text
                $output->write('<hl>'.substr($matches[$ofs], $i).'</hl>');
                // Restore cursor position
                $output->write("\0338");
            }
        }

        // Reset stty so it behaves normally again
        shell_exec(sprintf('stty %s', $sttyMode));

        return $ret;
    }

    /**
     * Gets a hidden response from user.
     *
     * @param OutputInterface $output
     * @param resource        $inputStream
     *
     * @return string The answer
     *
     * @throws \RuntimeException In case the fallback is deactivated and the response cannot be hidden
     */
    private function getHiddenResponse(OutputInterface $output, $inputStream)
    {
        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            $exe = __DIR__.'/../Resources/bin/hiddeninput.exe';

            // handle code running from a phar
            if ('phar:' === substr(__FILE__, 0, 5)) {
                $tmpExe = sys_get_temp_dir().'/hiddeninput.exe';
                copy($exe, $tmpExe);
                $exe = $tmpExe;
            }

            $value = rtrim(shell_exec($exe));
            $output->writeln('');

            if (isset($tmpExe)) {
                unlink($tmpExe);
            }

            return $value;
        }

        if ($this->sttyAvailable) {
            $sttyMode = shell_exec('stty -g');

            shell_exec('stty -echo');
            $value = fgets($inputStream, 4096);
            shell_exec(sprintf('stty %s', $sttyMode));

            if (false === $value) {
                throw new \RuntimeException('Aborted');
            }

            $value = trim($value);
            $output->writeln('');

            return $value;
        }

        if (false !== $shell = $this->getShell()) {
            $readCmd = $shell === 'csh' ? 'set mypassword = $<' : 'read -r mypassword';
            $command = sprintf("/usr/bin/env %s -c 'stty -echo; %s; stty echo; echo \$mypassword'", $shell, $readCmd);
            $value = rtrim(shell_exec($command));
            $output->writeln('');

            return $value;
        }

        throw new \RuntimeException('Unable to hide the response.');
    }

    /**
     * Returns a valid unix shell.
     *
     * @return string|bool The valid shell name, false in case no valid shell is found
     */
    private function getShell()
    {
        if (null !== self::$shell) {
            return self::$shell;
        }

        self::$shell = false;

        if (file_exists('/usr/bin/env')) {
            // handle other OSs with bash/zsh/ksh/csh if available to hide the answer
            $test = "/usr/bin/env %s -c 'echo OK' 2> /dev/null";
            foreach (array('bash', 'zsh', 'ksh', 'csh') as $sh) {
                if ('OK' === rtrim(shell_exec(sprintf($test, $sh)))) {
                    self::$shell = $sh;
                    break;
                }
            }
        }

        return self::$shell;
    }
}
