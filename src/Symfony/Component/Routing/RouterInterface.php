<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;

/**
 * RouterInterface is the interface that all Router classes must implements.
 *
 * This interface is the concatenation of UrlMatcherInterface and UrlGeneratorInterface.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
interface RouterInterface extends UrlMatcherInterface, UrlGeneratorInterface
{
}
