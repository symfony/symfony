<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Middleware\Debug;

/**
 * @author Laurent VOULLEMIER <laurent.voullemier@gmail.com>
 */
class DebugDataHolder
{
    private array $data = [];

    public function addQuery(string $connectionName, Query $query): void
    {
        $this->data[$connectionName][] = [
            'sql' => $query->getSql(),
            'params' => $query->getParams(),
            'types' => $query->getTypes(),
            // stop() may not be called at this point
            'executionMS' => $query->getDuration(...),
            'ranOnPrimary' => $query->ranOnPrimary(...),
        ];
    }

    public function getData(): array
    {
        foreach ($this->data as $connectionName => $dataForConn) {
            foreach ($dataForConn as $idx => $data) {
                if (\is_callable($data['executionMS'])) {
                    $this->data[$connectionName][$idx]['executionMS'] = $data['executionMS']();
                }
                if (\is_callable($data['ranOnPrimary'])) {
                    $this->data[$connectionName][$idx]['ranOnPrimary'] = $data['ranOnPrimary']();
                }
            }
        }

        return $this->data;
    }

    public function reset(): void
    {
        $this->data = [];
    }
}
