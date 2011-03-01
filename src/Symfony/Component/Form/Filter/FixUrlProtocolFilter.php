<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Filter;

use Symfony\Component\Form\FieldInterface;
use Symfony\Component\Form\Filters;

/**
 * Adds a protocol to a URL if it doesn't already have one.
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
class FixUrlProtocolFilter implements FilterInterface
{
    private $defaultProtocol;

    public function __construct($defaultProtocol = 'http')
    {
        $this->defaultProtocol = $defaultProtocol;
    }

    public function filterBoundData($data)
    {
        if ($this->defaultProtocol && $data && !preg_match('~^\w+://~', $data)) {
            $data = $this->defaultProtocol . '://' . $data;
        }

        return $data;
    }

    public function getSupportedFilters()
    {
        return Filters::filterBoundData;
    }
}