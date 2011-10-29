<?php

namespace Symfony\Component\Serializer\Encoder;


/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Defines the interface of encoders that will normalize data themselves
 *
 * Implementing this interface essentially just tells the Serializer that the
 * data should not be pre-normalized before being passed to this Encoder.
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
interface NormalizationAwareInterface
{
}
