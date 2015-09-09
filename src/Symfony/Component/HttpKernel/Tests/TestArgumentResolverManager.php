<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentResolverManager;

/**
 * @author Wouter J <wouter@wouterj.nl>
 */
class TestArgumentResolverManager extends ArgumentResolverManager
{
    public function getArguments(Request $request, $controller)
    {
        return array($request);
    }
}
