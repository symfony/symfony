<input type="file"
    id="<?php echo $context['file']->get('id') ?>"
    name="<?php echo $context['file']->get('name') ?>"
    <?php if ($context['file']->get('disabled')): ?>disabled="disabled"<?php endif ?>
    <?php if ($context['file']->get('required')): ?>required="required"<?php endif ?>
    <?php if ($context['file']->get('class')): ?>class="<?php echo $context['file']->get('class') ?>"<?php endif ?>
/>

<?php echo $view['form']->widget($context['token']) ?>
<?php echo $view['form']->widget($context['name']) ?>
