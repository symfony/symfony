<?php

class LimeParserTap extends LimeParser
{
  public function __construct(LimeOutputInterface $output)
  {
    parent::__construct($output);
  }

  public function parse($data)
  {
    $this->buffer .= $data;

    while (!$this->done())
    {
      if (preg_match('/^(.+)\n/', $this->buffer, $matches))
      {
        $this->buffer = substr($this->buffer, strlen($matches[0]));
        $line = $matches[0];

        if (preg_match('/^1\.\.(\d+)\n/', $line, $matches))
        {
          $this->output->plan((int)$matches[1]);
        }
        else if (preg_match('/^ok \d+( - (.+?))?( # (SKIP|TODO)( .+)?)?\n/', $line, $matches))
        {
          $message = count($matches) > 2 ? $matches[2] : '';

          if (count($matches) > 3)
          {
            if ($matches[4] == 'SKIP')
            {
              $this->output->skip($message, '', '');
            }
            else
            {
              $this->output->todo($message, '', '');
              $this->output->warning('TODOs are expected to have status "not ok"', '', '');
            }
          }
          else
          {
            $this->output->pass($message, '', '');
          }
        }
        else if (preg_match('/^not ok \d+( - (.+?))?( # (SKIP|TODO)( .+)?)?\n/', $line, $matches))
        {
          $message = count($matches) > 2 ? $matches[2] : '';

          if (count($matches) > 3)
          {
            if ($matches[4] == 'SKIP')
            {
              $this->output->skip($message, '', '');
              $this->output->warning('Skipped tests are expected to have status "ok"', '', '');
            }
            else
            {
              $this->output->todo($message, '', '');
            }
          }
          else
          {
            $this->output->fail($message, '', '');
          }
        }
      }
      else
      {
        break;
      }
    }

    $this->clearErrors();
  }

  public function done()
  {
    return empty($this->buffer);
  }
}