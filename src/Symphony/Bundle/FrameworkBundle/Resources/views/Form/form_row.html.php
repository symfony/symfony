<div>
    <?php $widgetAttr = empty($help) ? array() : array('attr' => array('aria-describedby' => $id.'_help')); ?>
    <?php echo $view['form']->label($form); ?>
    <?php echo $view['form']->errors($form); ?>
    <?php echo $view['form']->widget($form, $widgetAttr); ?>
    <?php echo $view['form']->help($form); ?>
</div>
