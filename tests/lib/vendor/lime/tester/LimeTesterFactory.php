<?php

/*
 * This file is part of the Lime framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 * (c) Bernhard Schussek <bernhard.schussek@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

class LimeTesterFactory
{
  protected
    $testers = array(
      'null'      => 'LimeTesterScalar',
      'integer'   => 'LimeTesterInteger',
      'boolean'   => 'LimeTesterScalar',
      'string'    => 'LimeTesterString',
      'double'    => 'LimeTesterDouble',
      'array'     => 'LimeTesterArray',
      'object'    => 'LimeTesterObject',
      'resource'  => 'LimeTesterResource',
      'Exception' => 'LimeTesterException',
    );

  public function create($value)
  {
    $type = null;

    if (is_null($value))
    {
      $type = 'null';
    }
    else if (is_object($value) && array_key_exists(get_class($value), $this->testers))
    {
      $type = get_class($value);
    }
    else if (is_object($value))
    {
      $class = new ReflectionClass($value);

      foreach ($class->getInterfaces() as $interface)
      {
        if (array_key_exists($interface->getName(), $this->testers))
        {
          $type = $interface->getName();
          break;
        }
      }

      $parentClass = $class;

      while ($parentClass = $parentClass->getParentClass())
      {
        if (array_key_exists($parentClass->getName(), $this->testers))
        {
          $type = $parentClass->getName();
          break;
        }
      }

      if (!empty($type))
      {
        // cache the tester
        $this->testers[$class->getName()] = $this->testers[$type];
      }
    }

    if (empty($type))
    {
      if (array_key_exists(gettype($value), $this->testers))
      {
        $type = gettype($value);
      }
      else
      {
        throw new InvalidArgumentException(sprintf('No tester is registered for type "%s"', gettype($value)));
      }
    }

    $class = $this->testers[$type];

    return new $class($value);
  }

  public function register($type, $tester)
  {
    if (!class_exists($tester))
    {
      throw new InvalidArgumentException(sprintf('The class "%s" does not exist', $tester));
    }

    $class = new ReflectionClass($tester);

    if (!$class->implementsInterface('LimeTesterInterface'))
    {
      throw new InvalidArgumentException('Testers must implement "LimeTesterInterface"');
    }

    $this->testers[$type] = $tester;
  }

  public function unregister($type)
  {
    if (array_key_exists($type, $this->testers))
    {
      unset($this->testers[$type]);
    }
  }

}