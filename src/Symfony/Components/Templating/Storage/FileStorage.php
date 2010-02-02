<?php

namespace Symfony\Components\Templating\Storage;

/*
 * This file is part of the symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * FileStorage represents a template stored on the filesystem.
 *
 * @package    symfony
 * @subpackage templating
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class FileStorage extends Storage
{
  public function getContent()
  {
    return file_get_contents($this->template);
  }
}
