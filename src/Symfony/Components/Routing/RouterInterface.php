<?php

namespace Symfony\Components\Routing;

use Symfony\Components\Routing\Generator\UrlGeneratorInterface;
use Symfony\Components\Routing\Matcher\UrlMatcherInterface;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * RouterInterface is the interface that all Router classes must implements.
 *
 * This interface is the concatenation of UrlMatcherInterface and UrlGeneratorInterface.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
interface RouterInterface extends UrlMatcherInterface, UrlGeneratorInterface
{
}
