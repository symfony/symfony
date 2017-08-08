<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\DataCollector;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

/**
 * Collects environment variables loaded by the Dotenv component.
 *
 * @author Oleg Voronkovich <oleg-voronkovich@yandex.ru>
 */
class DotenvDataCollector extends DataCollector
{
    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        $this->data['envs'] = array();

        $loadedVars = array_filter(explode(',', getenv('SYMFONY_DOTENV_VARS')));

        foreach ($loadedVars as $var) {
            if (false !== getenv($var)) {
                $this->data['envs'][$var] = getenv($var);
            }
        }
    }

    /**
     * Gets loaded environment variables.
     *
     * @return array
     */
    public function getEnvs()
    {
        return $this->data['envs'];
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'dotenv';
    }
}
