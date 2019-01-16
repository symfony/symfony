<?php echo $this->get('actions')->render($this->get('actions')->controller('Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\Controller\FragmentController::inlinedAction', [
            'options' => [
                'bar' => $bar,
                'eleven' => 11,
            ],
        ]));
?>--<?php
        echo $this->get('actions')->render($this->get('actions')->controller('Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\Controller\FragmentController::customformatAction', ['_format' => 'html']));
?>--<?php
        echo $this->get('actions')->render($this->get('actions')->controller('Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\Controller\FragmentController::customlocaleAction', ['_locale' => 'es']));
?>--<?php
        $app->getRequest()->setLocale('fr');
        echo $this->get('actions')->render($this->get('actions')->controller('Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\Controller\FragmentController::forwardlocaleAction'));
?>
