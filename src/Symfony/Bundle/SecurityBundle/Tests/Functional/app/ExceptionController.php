<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Functional\app;

use Symfony\Component\ErrorRenderer\ErrorRenderer;
use Symfony\Component\ErrorRenderer\ErrorRenderer\HtmlErrorRenderer;
use Symfony\Component\ErrorRenderer\ErrorRenderer\JsonErrorRenderer;
use Symfony\Component\ErrorRenderer\Exception\FlattenException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ExceptionController
{
    private $errorRenderer;

    public function __construct()
    {
        $this->errorRenderer = new ErrorRenderer([
            new HtmlErrorRenderer(),
            new JsonErrorRenderer(),
        ]);
    }

    public function __invoke(Request $request, FlattenException $exception)
    {
        return new Response($this->errorRenderer->render($exception, $request->getPreferredFormat()), $exception->getStatusCode());
    }
}
