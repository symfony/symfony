<!DOCTYPE html>
<html>
<head>
    <meta charset="<?php echo $this->charset; ?>" />
    <meta name="robots" content="noindex,nofollow,noarchive" />
    <title>An Error Occurred: <?php echo $statusText; ?></title>
    <style><?php echo $this->include('assets/css/error.css'); ?></style>
</head>
<body>
<div class="container">
    <h1>Oops! An Error Occurred</h1>
    <h2>The server returned a "<?php echo $statusCode; ?> <?php echo $statusText; ?>".</h2>

    <p>
        Something is broken. Please let us know what you were doing when this error occurred.
        We will fix it as soon as possible. Sorry for any inconvenience caused.
    </p>
</div>
</body>
</html>
