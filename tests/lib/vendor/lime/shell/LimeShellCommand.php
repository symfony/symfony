<?php

class LimeShellCommand
{
  protected
    $command    = null,
    $status     = null,
    $output     = '',
    $errors     = '',
    $errorFile  = '';

  public function __construct($file, array $arguments = array())
  {
    foreach ($arguments as $argument => $value)
    {
      $arguments[$argument] = '--'.$argument;

      if ($value !== true)
      {
        if (!is_string($value))
        {
          $value = var_export($value, true);
        }

        $arguments[$argument] .= '='.escapeshellarg($value);
      }
    }

    $this->errorFile = tempnam(sys_get_temp_dir(), 'lime');

    // see http://trac.symfony-project.org/ticket/5437 for the explanation on the weird "cd" thing
    $this->command = sprintf(
      'cd & %s %s %s 2>%s',
      escapeshellarg(LimeShell::getExecutable()),
      escapeshellarg($file),
      implode(' ', $arguments),
      $this->errorFile
    );
  }

  public function execute()
  {
    // clear old errors
    $this->errors = '';
    file_put_contents($this->errorFile, '');

    ob_start();
    passthru($this->command, $this->status);
    $this->output = ob_get_clean();
    $this->errors = file_get_contents($this->errorFile);
  }

  public function getStatus()
  {
    return $this->status;
  }

  public function getOutput()
  {
    return $this->output;
  }

  public function getErrors()
  {
    return $this->errors;
  }
}