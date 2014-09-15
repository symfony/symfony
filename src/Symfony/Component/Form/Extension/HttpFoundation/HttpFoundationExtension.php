<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\HttpFoundation;

use Symfony\Component\Form\AbstractExtension;
use Symfony\Component\Form\Util\ServerParams;

/**
 * Integrates the HttpFoundation component with the Form library.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class HttpFoundationExtension extends AbstractExtension
{
    /**
     * @var ServerParams
     */
    private $serverParams;

    public function __construct(ServerParams $serverParams = null)
    {
        $this->serverParams = $serverParams;
    }

    protected function loadTypeExtensions()
    {
        return array(
            new Type\FormTypeHttpFoundationExtension($this->serverParams),
        );
    }
}
