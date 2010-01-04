<?php

class LimeShellProcess extends LimeShellCommand
{
  protected
    $handle         = null,
    $errorHandle    = null;

  public function execute()
  {
    $this->errorHandle = fopen($this->errorFile, 'w+'); // clear error file
    $this->handle = popen($this->command, 'r');
  }

  public function getStatus()
  {
    throw new BadMethodCallException('Status is not supported by processes');
  }

  public function getOutput()
  {
    return feof($this->handle) ? '' : fread($this->handle, 1024);
  }

  public function getErrors()
  {
    // don't check feof here, for some reason some errors get dropped then
    return fread($this->errorHandle, 1024);
  }

  public function isClosed()
  {
    return feof($this->handle) && feof($this->errorHandle);
  }
}