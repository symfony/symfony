<?php

/*
 * This file is part of the Lime test framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 * (c) Bernhard Schussek <bernhard.schussek@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Reads template files and parses them with a set of template variables.
 *
 * @package    Lime
 * @author     Bernhard Schussek <bernhard.schussek@symfony-project.com>
 * @version    SVN: $Id: LimeMockTemplate.php 23701 2009-11-08 21:23:40Z bschussek $
 */
class LimeMockTemplate
{
  private
    $parameters   = array(),
    $filename     = '';

  /**
   * Constructor.
   *
   * Configures this template to use the given file.
   *
   * @param string $filename
   */
  public function __construct($filename)
  {
    $this->filename = $filename;
  }

  /**
   * Renders the file of this template with the given parameters.
   *
   * The parameters are made available in the template and can be accessed there
   * as normal PHP variables. The template is parsed and the output of the
   * template is returned.
   *
   * @param  array $parameters
   * @return string
   */
  public function render(array $parameters)
  {
    ob_start();
    extract($parameters);
    include $this->filename;

    return ob_get_clean();
  }

}