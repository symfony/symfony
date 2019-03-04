<tr>
    <?php $widgetAttr = empty($help) ? [] : ['attr' => ['aria-describedby' => $id.'_help']]; ?>
    <td>
        <?php echo $view['form']->label($form); ?>
    </td>
    <td>
        <?php echo $view['form']->errors($form); ?>
        <?php echo $view['form']->widget($form, $widgetAttr); ?>
        <?php echo $view['form']->help($form); ?>
    </td>
</tr>
