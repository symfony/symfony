<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<?php $path = '' ?>

<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>

<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta name="title" content="symfony project" />
<meta name="robots" content="index, follow" />
<meta name="description" content="symfony project" />
<meta name="keywords" content="symfony, project" />
<meta name="language" content="en" />
<title>symfony project</title>

<link rel="shortcut icon" href="/favicon.ico" />
<link rel="stylesheet" type="text/css" media="screen" href="<?php echo $path ?>/sf/sf_default/css/screen.css" />
<!--[if lt IE 7.]>
<link rel="stylesheet" type="text/css" media="screen" href="<?php echo $path ?>/sf/sf_default/css/ie.css" />
<![endif]-->

</head>
<body>
<div class="sfTContainer">
    <a title="symfony website" href="http://www.symfony-project.org/"><img alt="symfony PHP Framework" class="sfTLogo" src="<?php echo $path ?>/sf/sf_default/images/sfTLogo.png" height="39" width="186" /></a>
    <div class="sfTMessageContainer sfTAlert">
        <img alt="page not found" class="sfTMessageIcon" src="<?php echo $path ?>/sf/sf_default/images/icons/tools48.png" height="48" width="48" />
        <div class="sfTMessageWrap">
            <h1>Oops! An Error Occurred</h1>
            <h5>The server returned a "<?php echo $manager->getStatusCode() ?> <?php echo $manager->getStatusText() ?>".</h5>
        </div>
    </div>

    <dl class="sfTMessageInfo">
        <dt>Something is broken</dt>
        <dd>Please e-mail us at [email] and let us know what you were doing when this error occurred. We will fix it as soon as possible.
        Sorry for any inconvenience caused.</dd>

        <dt>What's next</dt>
        <dd>
            <ul class="sfTIconList">
                <li class="sfTLinkMessage"><a href="javascript:history.go(-1)">Back to previous page</a></li>
                <li class="sfTLinkMessage"><a href="/">Go to Homepage</a></li>
            </ul>
        </dd>
    </dl>
</div>
</body>
</html>
