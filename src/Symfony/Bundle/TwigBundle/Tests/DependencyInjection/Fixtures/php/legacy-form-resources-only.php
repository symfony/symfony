<?php

$container->loadFromExtension('twig', array(
    'form' => array(
        'resources' => array(
            'form_table_layout.html.twig',
            'MyBundle:Form:my_theme.html.twig',
        ),
    ),
));
