<?php $view->render('FrameworkBundle:Form:form_widget.html.php', array('form' => $form)) ?>

<?php if (isset($prototype)): ?>
<script type="text/html" id="<?php echo $view->escape($id) ?>_prototype"><?php echo $view['form']->row($prototype) ?></script>
<?php endif; ?>
