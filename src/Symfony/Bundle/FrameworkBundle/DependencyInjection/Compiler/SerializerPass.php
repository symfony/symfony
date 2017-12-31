<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler;

@trigger_error(sprintf('The %s class is deprecated since Symfony 3.3 and will be removed in 4.0. Use Symfony\Component\Serializer\DependencyInjection\SerializerPass instead.', SerializerPass::class), E_USER_DEPRECATED);

use Symfony\Component\Serializer\DependencyInjection\SerializerPass as BaseSerializerPass;

/**
 * Adds all services with the tags "serializer.encoder" and "serializer.normalizer" as
 * encoders and normalizers to the Serializer service.
 *
 * @deprecated since version 3.3, to be removed in 4.0. Use {@link BaseSerializerPass} instead.
 *
 * @author Javier Lopez <f12loalf@gmail.com>
 */
class SerializerPass extends BaseSerializerPass
{
}
