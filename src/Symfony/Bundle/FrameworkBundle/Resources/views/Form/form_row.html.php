<div>
    <?php $widgetAttr = empty($help) ? [] : ['attr' => ['aria-describedby' => $id.'_help']]; ?>
    <?php echo $view['form']->label($form); ?>
    <?php echo $view['form']->errors($form); ?>
    <?php echo $view['form']->widget($form, $widgetAttr); ?>
    <?php echo $view['form']->help($form); ?>
</div>
