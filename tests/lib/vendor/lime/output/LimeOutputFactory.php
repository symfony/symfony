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

class LimeOutputFactory
{
  protected
    $options = array();

  public function __construct(array $options)
  {
    $this->options = array_merge(array(
      'serialize'     => false,
      'force_colors'  => false,
      'base_dir'      => null,
    ), $options);
  }

  public function create($name)
  {
    $colorizer = LimeColorizer::isSupported() || $this->options['force_colors'] ? new LimeColorizer() : null;
    $printer = new LimePrinter($colorizer);

    switch ($name)
    {
      case 'raw':
        return new LimeOutputRaw();
      case 'xml':
        return new LimeOutputXml();
      case 'array':
        return new LimeOutputArray($this->options['serialize']);
      case 'summary':
        return new LimeOutputConsoleSummary($printer, $this->options);
      case 'tap':
      default:
        return new LimeOutputTap($printer, $this->options);
    }
  }
}