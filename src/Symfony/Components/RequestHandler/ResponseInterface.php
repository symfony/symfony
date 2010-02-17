<?php

namespace Symfony\Components\RequestHandler;

/*
 * This file is part of the symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * ResponseInterface is the interface that all server response classes must implement.
 *
 * @package    symfony
 * @subpackage request_handler
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
interface ResponseInterface
{
  function send();
}
