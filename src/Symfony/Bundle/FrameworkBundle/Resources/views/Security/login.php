<?php if ($error): ?>
    <div><?php echo $error ?></div>
<?php endif; ?>

<form action="<?php echo $view['router']->generate('_security_check') ?>" method="post">
    <label for="username">Username:</label>

    <input type="text" id="username" name="_username" value="<?php echo $last_username ?>" />
    <label for="password">Password:</label>
    <input type="password" id="password" name="_password" />

    <input type="submit" name="login" />
</form>
