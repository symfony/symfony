<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Templating;

use Symfony\Component\HttpFoundation\Response;

/**
 * @author Pierre du Plessis <pdples@gmail.com>
 */
interface TemplatedResponseInterface
{
    /**
     * @param EngineInterface $templating
     *
     * @return Response
     */
    public function getResponse(EngineInterface $templating);
}
