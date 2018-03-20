<tr>
    <?php $widgetAtt = empty($help) ? array() : array('attr' => array('aria-describedby' => $id.'_help')); ?>
    <td>
        <?php echo $view['form']->label($form); ?>
    </td>
    <td>
        <?php echo $view['form']->errors($form); ?>
        <?php echo $view['form']->widget($form, $widgetAtt); ?>
        <?php echo $view['form']->help($form); ?>
    </td>
</tr>
