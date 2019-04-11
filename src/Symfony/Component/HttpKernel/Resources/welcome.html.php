<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8" />
    <title>Welcome!</title>
    <style>
        body { background: #F5F5F5; font: 18px/1.5 sans-serif; }
        h1, h2 { line-height: 1.2; margin: 0 0 .5em; }
        h1 { font-size: 36px; }
        h2 { font-size: 21px; margin-bottom: 1em; }
        p { margin: 0 0 1em 0; }
        a { color: #0000F0; }
        a:hover { text-decoration: none; }
        code { background: #F5F5F5; max-width: 100px; padding: 2px 6px; word-wrap: break-word; }
        #wrapper { background: #FFF; margin: 1em auto; max-width: 800px; width: 95%; }
        #container { padding: 2em; }
        #welcome, #status { margin-bottom: 2em; }
        #welcome h1 span { display: block; font-size: 75%; }
        #comment { font-size: 14px; text-align: center; color: #777777; background: #FEFFEA; padding: 10px; }
        #comment p { margin-bottom: 0; }
        #icon-status, #icon-book { float: left; height: 64px; margin-right: 1em; margin-top: -4px; width: 64px; }
        #icon-book { display: none; }

        @media (min-width: 768px) {
            #wrapper { width: 80%; margin: 2em auto; }
            #icon-book { display: inline-block; }
            #status a, #next a { display: block; }

            @-webkit-keyframes fade-in { 0% { opacity: 0; } 100% { opacity: 1; } }
            @keyframes fade-in { 0% { opacity: 0; } 100% { opacity: 1; } }
            .sf-toolbar { opacity: 0; -webkit-animation: fade-in 1s .2s forwards; animation: fade-in 1s .2s forwards;}
        }
    </style>
</head>
<body>
<div id="wrapper">
    <div id="container">
        <div id="welcome">
            <h1><span>Welcome to</span> Symfony <?php echo $version; ?></h1>
        </div>

        <div id="status">
            <p>
                <svg id="icon-status" width="1792" height="1792" viewBox="0 0 1792 1792" xmlns="http://www.w3.org/2000/svg"><path d="M1671 566q0 40-28 68l-724 724-136 136q-28 28-68 28t-68-28l-136-136-362-362q-28-28-28-68t28-68l136-136q28-28 68-28t68 28l294 295 656-657q28-28 68-28t68 28l136 136q28 28 28 68z" fill="#759E1A"/></svg>

                Your application is now ready. You can start working on it at:<br>
                <code><?php echo $baseDir; ?></code>
            </p>
        </div>

        <div id="next">
            <h2>What's next?</h2>
            <p>
                <svg id="icon-book" xmlns="http://www.w3.org/2000/svg" viewBox="-12.5 9 64 64"><path fill="#AAA" d="M6.8 40.8c2.4.8 4.5-.7 4.9-2.5.2-1.2-.3-2.1-1.3-3.2l-.8-.8c-.4-.5-.6-1.3-.2-1.9.4-.5.9-.8 1.8-.5 1.3.4 1.9 1.3 2.9 2.2-.4 1.4-.7 2.9-.9 4.2l-.2 1c-.7 4-1.3 6.2-2.7 7.5-.3.3-.7.5-1.3.6-.3 0-.4-.3-.4-.3 0-.3.2-.3.3-.4.2-.1.5-.3.4-.8 0-.7-.6-1.3-1.3-1.3-.6 0-1.4.6-1.4 1.7s1 1.9 2.4 1.8c.8 0 2.5-.3 4.2-2.5 2-2.5 2.5-5.4 2.9-7.4l.5-2.8c.3 0 .5.1.8.1 2.4.1 3.7-1.3 3.7-2.3 0-.6-.3-1.2-.9-1.2-.4 0-.8.3-1 .8-.1.6.8 1.1.1 1.5-.5.3-1.4.6-2.7.4l.3-1.3c.5-2.6 1-5.7 3.2-5.8.2 0 .8 0 .8.4 0 .2 0 .2-.2.5s-.3.4-.2.7c0 .7.5 1.1 1.2 1.1.9 0 1.2-1 1.2-1.4 0-1.2-1.2-1.8-2.6-1.8-1.5.1-2.8.9-3.7 2.1-1.1 1.3-1.8 2.9-2.3 4.5-.9-.8-1.6-1.8-3.1-2.3-1.1-.7-2.3-.5-3.4.3-.5.4-.8 1-1 1.6-.4 1.5.4 2.9.8 3.4l.9 1c.2.2.6.8.4 1.5-.3.8-1.2 1.3-2.1 1-.4-.2-1-.5-.9-.9.1-.2.2-.3.3-.5s.1-.3.1-.3c.2-.6-.1-1.4-.7-1.6-.6-.2-1.2 0-1.3.8 0 .7.4 2.3 2.5 3.1zm39.3-19.9c0-4.2-3.2-7.5-7.1-7.5h-3.8c-.4-2.6-2.5-4.4-5-4.4l-32.5.1c-2.8.1-4.9 2.4-4.9 5.4l.2 44.1c0 4.8 8.1 13.9 11.6 14.1l34.7-.1c3.9 0 7-3.4 7-7.6l-.2-44.1zM-.3 36.4c0-8.6 6.5-15.6 14.5-15.6s14.5 7 14.5 15.6S22.1 52 14.2 52C6.1 52-.3 45-.3 36.4zm42.4 28.7c0 1.8-1.5 3.1-3.1 3.1H4.6c-.7 0-3-1.8-4.5-4.4h30.4c2.8 0 5-2.4 5-5.4V17.9h3.7c1.6 0 2.9 1.4 2.9 3.1v44.1z"/></svg>

                Read the documentation to learn
                <a href="https://symfony.com/doc/<?php echo $docVersion; ?>/page_creation.html">
                    How to create your first page in Symfony
                </a>
            </p>
        </div>
    </div>
    <div id="comment">
        <p>
            You're seeing this page because debug mode is enabled and you haven't configured any homepage URL.
        </p>
    </div>
</div>
</body>
</html>
