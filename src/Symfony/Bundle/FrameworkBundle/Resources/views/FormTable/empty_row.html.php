<?php $translation_domain = $translation_domain ?: 'sf_form'; ?>
<table <?php echo $view['form']->block($form, 'widget_container_attributes') ?>>
    <tr>
        <td colspan="2">
            <em>
                <?php echo $view->escape(false !== $translation_domain
                    ? $view['translator']->trans($empty_view, array(), $translation_domain)
                    : $empty_view
                ); ?>
            </em>
        </td>
    </tr>
</table>
