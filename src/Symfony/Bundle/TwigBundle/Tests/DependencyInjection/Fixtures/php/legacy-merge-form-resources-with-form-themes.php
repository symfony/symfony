<?php

$container->loadFromExtension('twig', array(
    'form' => array(
        'resources' => array(
            'form_table_layout.html.twig',
            'MyBundle:Form:my_theme.html.twig',
        ),
    ),
    'form_themes' => array(
        'FooBundle:Form:bar.html.twig',
    ),
));
