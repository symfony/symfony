<div>
    <?php echo $view['form']->label($form); ?>
    <?php echo $view['form']->errors($form); ?>
    <?php echo $view['form']->widget($form, array('helpBlockDisplayed' => true)); ?>
    <?php echo $view['form']->help($form); ?>
</div>
