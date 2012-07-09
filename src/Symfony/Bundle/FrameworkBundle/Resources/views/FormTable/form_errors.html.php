<?php if (0 < count($errors)) : ?>
<tr>
    <td colspan="2">
        <?php echo $view['form']->renderBlock('field_errors'); ?>
    </td>
</tr>
<?php endif;
