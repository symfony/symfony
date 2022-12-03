<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Tests\Fixtures\Psr4Controllers\SubNamespace;

use Symfony\Component\HttpFoundation\Response;

/**
 * An irrelevant class.
 *
 * This fixture is not referenced anywhere. Its presence makes sure, classes without attributes are silently ignored
 * when loading routes from a directory.
 */
final class IrrelevantClass
{
    public function irrelevantAction(): Response
    {
        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
