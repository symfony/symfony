<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\OptionsResolver\Exception;

use Symphony\Component\OptionsResolver\Debug\OptionsResolverIntrospector;

/**
 * Thrown when trying to introspect an option definition property
 * for which no value was configured inside the OptionsResolver instance.
 *
 * @see OptionsResolverIntrospector
 *
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
class NoConfigurationException extends \RuntimeException implements ExceptionInterface
{
}
