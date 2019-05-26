<?php

// Config.php sets some variables for use
// Replace with your data and save as config.php
//

return (object) array(
// Site settings
    'siteUrl' => 'http://localhost:8888/grav-admin/', // the URL for your site - note trailing slash
    'timezone' => 'Europe/Rome', // http://php.net/manual/en/timezones.php

// Config Block for writing to Grav
// Will depend on your Grav setup
'notePath' => '/grav-admin/user/pages/06.stream/', // Path to parent folder
'noteParent' => '06.stream/', // Name of parent folder

// Overcast settings
'email' => 'YOUR OVERCAST USERNAME',
'pw' => 'YOUR OVERCAST PASSWORD',
'feedUrl' => "https://overcast.fm/account/export_opml/extended",
'loginurl' => "https://overcast.fm/login",
'cookie' => "cookie.txt",
);
