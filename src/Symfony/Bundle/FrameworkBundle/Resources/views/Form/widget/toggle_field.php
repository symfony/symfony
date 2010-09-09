<?php echo $view->render('FrameworkBundle:Form:widget/input_field.php', array(
        'field'      => $field,
        'origin'     => $origin,
        'attributes' => $attributes,
        'generator'  => $generator,
    ))
?>

<?php if ($label = $origin->getOption('label')): ?>
    <?php echo $generator->contentTag('label', $view['translator']->trans($label), array('for' => $origin->getId())) ?>
<?php endif; ?>
