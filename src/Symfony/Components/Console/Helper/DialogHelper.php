<?php

namespace Symfony\Components\Console\Helper;

use Symfony\Components\Console\Output\OutputInterface;

/*
 * This file is part of the symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * The Dialog class provides helpers to interact with the user.
 *
 * @package    symfony
 * @subpackage console
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class DialogHelper extends Helper
{
  /**
   * Asks a question to the user.
   *
   * @param OutputInterface $output
   * @param string|array    $question The question to ask
   * @param string          $default  The default answer if none is given by the user
   *
   * @param string The user answer
   */
  public function ask(OutputInterface $output, $question, $default = null)
  {
    // @codeCoverageIgnoreStart
    $output->writeln($question);

    $ret = trim(fgets(STDIN));

    return $ret ? $ret : $default;
    // @codeCoverageIgnoreEnd
  }

  /**
   * Asks a confirmation to the user.
   *
   * The question will be asked until the user answer by nothing, yes, or no.
   *
   * @param OutputInterface $output
   * @param string|array    $question The question to ask
   * @param Boolean         $default  The default answer if the user enters nothing
   *
   * @param Boolean true if the user has confirmed, false otherwise
   */
  public function askConfirmation(OutputInterface $output, $question, $default = true)
  {
    // @codeCoverageIgnoreStart
    $answer = 'z';
    while ($answer && !in_array(strtolower($answer[0]), array('y', 'n')))
    {
      $answer = $this->ask($output, $question);
    }

    if (false === $default)
    {
      return $answer && 'y' == strtolower($answer[0]);
    }
    else
    {
      return !$answer || 'y' == strtolower($answer[0]);
    }
    // @codeCoverageIgnoreEnd
  }

  /**
   * Asks for a value and validates the response.
   *
   * @param OutputInterface $output
   * @param string|array    $question
   * @param Closure         $validator
   * @param integer         $attempts Max number of times to ask before giving up (false by default, which means infinite)
   *
   * @return mixed
   */
  public function askAndValidate(OutputInterface $output, $question, \Closure $validator, $attempts = false)
  {
    // @codeCoverageIgnoreStart
    $error = null;
    while (false === $attempts || $attempts--)
    {
      if (null !== $error)
      {
        $output->writeln($this->getHelperSet()->get('formatter')->formatBlock($error->getMessage(), 'error'));
      }

      $value = $this->ask($output, $question, null);

      try
      {
        return $validator($value);
      }
      catch (\Exception $error)
      {
      }
    }

    throw $error;
    // @codeCoverageIgnoreEnd
  }

  /**
   * Returns the helper's canonical name
   */
  public function getName()
  {
    return 'dialog';
  }
}
