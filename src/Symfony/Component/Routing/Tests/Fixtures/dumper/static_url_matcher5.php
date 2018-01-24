<?php

// This file has been auto-generated by the Symfony Routing Component.

return array(
    array(
        array(
            null,
            array(
                null,
                array(
                    '/a',
                    array(array('path', '/a/11'), null, null, null, array('_route' => 'a_first')),
                    array(array('path', '/a/22'), null, null, null, array('_route' => 'a_second')),
                    array(array('path', '/a/333'), null, null, null, array('_route' => 'a_third')),
                ),
                array(array('match', '#^/(?P<param>[^/]++)$#s'), null, null, null, array('_route' => 'a_wildcard')),
                array(
                    '/a',
                    array(array('trim', '/a/44'), null, null, null, array('_route' => 'a_fourth')),
                    array(array('trim', '/a/55'), null, null, null, array('_route' => 'a_fifth')),
                    array(array('trim', '/a/66'), null, null, null, array('_route' => 'a_sixth')),
                ),
                array(array('start', '#^/nested/(?P<param>[^/]++)$#s', '/nested'), null, null, null, array('_route' => 'nested_wildcard')),
                array(
                    '/nested/group',
                    array(array('trim', '/nested/group/a'), null, null, null, array('_route' => 'nested_a')),
                    array(array('trim', '/nested/group/b'), null, null, null, array('_route' => 'nested_b')),
                    array(array('trim', '/nested/group/c'), null, null, null, array('_route' => 'nested_c')),
                ),
                array(
                    '/slashed/group',
                    array(array('trim', '/slashed/group'), null, null, null, array('_route' => 'slashed_a')),
                    array(array('trim', '/slashed/group/b'), null, null, null, array('_route' => 'slashed_b')),
                    array(array('trim', '/slashed/group/c'), null, null, null, array('_route' => 'slashed_c')),
                ),
            ),
        ),
    ),
    array(),
);
