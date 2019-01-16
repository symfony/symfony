<?php echo $this->get('actions')->render($this->get('actions')->controller('TestBundle:Fragment:inlined', [
            'options' => [
                'bar' => $bar,
                'eleven' => 11,
            ],
        ]));
?>--<?php
        echo $this->get('actions')->render($this->get('actions')->controller('TestBundle:Fragment:customformat', ['_format' => 'html']));
?>--<?php
        echo $this->get('actions')->render($this->get('actions')->controller('TestBundle:Fragment:customlocale', ['_locale' => 'es']));
?>--<?php
        $app->getRequest()->setLocale('fr');
        echo $this->get('actions')->render($this->get('actions')->controller('TestBundle:Fragment:forwardlocale'));
?>
