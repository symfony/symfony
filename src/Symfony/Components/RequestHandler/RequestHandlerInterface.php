<?php

namespace Symfony\Components\RequestHandler;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * RequestHandlerInterface.
 *
 * @package    Symfony
 * @subpackage Components_RequestHandler
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
interface RequestHandlerInterface
{
  /**
   * Handles a request to convert it to a response.
   *
   * @param  Request $request A Request instance
   * @param  Boolean $main    Whether this is the main request or not
   *
   * @return Response $response A Response instance
   *
   * @throws \Exception When Exception couldn't be caught by event processing
   */
  public function handle(Request $request = null, $main = true);

  /**
   * Gets the Request instance associated with the main request.
   *
   * @return Request A Request instance
   */
  public function getRequest();
}
