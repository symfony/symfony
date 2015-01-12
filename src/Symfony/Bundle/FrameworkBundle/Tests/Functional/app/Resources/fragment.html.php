<?php echo $this->get('actions')->render($this->get('actions')->controller('TestBundle:Fragment:inlined', array(
            'options' => array(
                'bar' => $bar,
                'eleven' => 11,
            ),
        )));
?>--<?php
        echo $this->get('actions')->render($this->get('actions')->controller('TestBundle:Fragment:customformat', array('_format' => 'html')));
?>--<?php
        echo $this->get('actions')->render($this->get('actions')->controller('TestBundle:Fragment:customlocale', array('_locale' => 'es')));
?>--<?php
        $app->getRequest()->setLocale('fr');
        echo $this->get('actions')->render($this->get('actions')->controller('TestBundle:Fragment:forwardlocale'));
?>
