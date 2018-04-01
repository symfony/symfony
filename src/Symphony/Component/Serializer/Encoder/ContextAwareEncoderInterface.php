<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Serializer\Encoder;

/**
 * Adds the support of an extra $context parameter for the supportsEncoding method.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
interface ContextAwareEncoderInterface extends EncoderInterface
{
    /**
     * {@inheritdoc}
     *
     * @param array $context options that encoders have access to
     */
    public function supportsEncoding($format, array $context = array());
}
