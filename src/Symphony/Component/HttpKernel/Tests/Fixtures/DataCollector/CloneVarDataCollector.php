<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\HttpKernel\Tests\Fixtures\DataCollector;

use Symphony\Component\HttpFoundation\Request;
use Symphony\Component\HttpFoundation\Response;
use Symphony\Component\HttpKernel\DataCollector\DataCollector;

class CloneVarDataCollector extends DataCollector
{
    private $varToClone;

    public function __construct($varToClone)
    {
        $this->varToClone = $varToClone;
    }

    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        $this->data = $this->cloneVar($this->varToClone);
    }

    public function reset()
    {
        $this->data = array();
    }

    public function getData()
    {
        return $this->data;
    }

    public function getName()
    {
        return 'clone_var';
    }
}
