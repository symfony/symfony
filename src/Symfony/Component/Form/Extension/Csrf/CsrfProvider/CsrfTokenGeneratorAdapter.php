<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Csrf\CsrfProvider;

use Symfony\Component\Security\Csrf\CsrfTokenGenerator;

/**
 * Adapter for using the new token generator with the old interface.
 *
 * @since  2.4
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @deprecated Deprecated since version 2.4, to be removed in Symfony 3.0.
 */
class CsrfTokenGeneratorAdapter extends CsrfTokenGenerator implements CsrfProviderInterface
{
}
