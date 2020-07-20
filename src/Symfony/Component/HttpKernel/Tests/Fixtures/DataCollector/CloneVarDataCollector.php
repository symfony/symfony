<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Fixtures\DataCollector;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

class CloneVarDataCollector extends DataCollector
{
    private $varToClone;

    public function __construct($varToClone)
    {
        $this->varToClone = $varToClone;
    }

    public function collect(Request $request, Response $response, \Throwable $exception = null)
    {
        $this->data = $this->cloneVar($this->varToClone);
    }

    public function reset()
    {
        $this->data = [];
    }

    public function getData()
    {
        return $this->data;
    }

    public function getName(): string
    {
        return 'clone_var';
    }
}
