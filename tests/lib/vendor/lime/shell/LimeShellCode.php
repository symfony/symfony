<?php

class LimeShellCode extends LimeShellCommand
{
  public function __construct($code)
  {
    $file = tempnam(sys_get_temp_dir(), 'lime');
    file_put_contents($file, '<?php '.$code);

    parent::__construct($file);
  }
}