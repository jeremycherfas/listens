<?php

/*
* Listens.php
* Downloads the extended OPML from Overcast
* Be careful not to request the file too often
* or you will run into rate-limiting issues.
* Use the saved versions of opml.json to test.
*
* Sorts
* Extracts only episodes that are played (not in progress) and
* not userDeleted
* Writes to file
*
*/

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Get variables from config
$config = include 'config.php';
$siteUrl = $config->siteUrl;
date_default_timezone_set($config->timezone); // Not sure I need this any more, as not doing date comparisons
$path = $config->notePath; //includes name of parent folder at end
$path = dirname(__DIR__) . $path; // This gets the path of the PARENT

$email = $config->email;
$pw = $config->pw;
$feedUrl = $config->feedUrl;
$loginurl = $config->loginurl;
$cookie = $config->cookie;

// Set filenames
$opml = __DIR__ . '/opml.xml'; // The extended OPML file
$old_opml = __DIR__ . '/old_opml.xml';
$bak_opml = __DIR__ . '/backup_opml.xml';
$new_episodesfile = __DIR__ . '/new_episodes.json'; // Episodes only from the OPML
$old_episodesfile = __DIR__ . '/old_episodes.json';
$bak_episodesfile = __DIR__ . '/backup_episodes.json';
$newfile = __DIR__ . '/newfile.txt';
$shinyfile = __DIR__ . '/shiny.json'; // The new episodes that will be written to file
$old_shinyfile = __DIR__ . '/old_shiny.json';


// Housekeeping
if (file_exists($old_opml)) {
    rename($old_opml, $bak_opml);
}
if (file_exists($opml)) {
    rename($opml, $old_opml);
}
if (file_exists($shinyfile)) {
	rename($shinyfile, $old_shinyfile);
}

// Authenticate at Overcast

//Create the curl authorisation payload
$overcast['body'] = 'then=podcasts&email=' . $email . '&password=' . $pw;
$overcast['headers'] =   array('Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
'Content-Type: application/x-www-form-urlencoded',
'Content-Length: ' . strlen($overcast['body']));

//Execute curl - somewhat voodoo but it works
$ch = curl_init();
curl_setopt($ch, CURLOPT_HEADER, false);
curl_setopt($ch, CURLOPT_NOBODY, false);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
curl_setopt($ch, CURLOPT_COOKIE, "cookiename=0");
curl_setopt($ch, CURLOPT_TIMEOUT, 60);
curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Macintosh; Intel Mac OS X 10.13; rv:65.0) Gecko/20100101 Firefox/65.0");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_URL, $loginurl);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
curl_setopt($ch, CURLOPT_POSTFIELDS, $overcast['body']);
curl_exec($ch);

// Get the OPML file and write it
// set the URL to the protected file and get it
curl_setopt($ch, CURLOPT_URL, $feedUrl);
$content = curl_exec($ch);
file_put_contents($opml, $content); // Overwrites opml.xml if exists
curl_close($ch);

// Moving on to parse-opml.php
$newfile = $opml;

// As we just stored $content as opml.xml it ought to be possible to avoid
// some of the manipulation below and create $newarray directly, but not
// right now.

if (file_exists($newfile)) {
    $xmlfile = file_get_contents($newfile); // read file into string
    $xml = simplexml_load_string($xmlfile); // convert into object
    $json = json_encode($xml); // convert into json
    $newarray = json_decode($json, true); //
} else {
    exit("Failed to open $newfile because it does not exist ");
}

// Extract the data
// The OPML file is not super easy to parse; this works
// The keys '@attributes' and 'Outline' appear at several levels of nesting

$finalarray = array();
$subscriptions = $newarray['body']['outline'][1]; // [1] is the subscriptions
$episodes = $subscriptions['outline'][1];
$podcastdetails = $episodes['outline'][1];

    $podcasts = $subscriptions['outline']; // the individual podcasts
    foreach ($podcasts as $podcast) { // loop through the podcasts

        // TEST for existence of $podcast['outline']
        if (isset($podcast['outline'][0])) {
            $finalarray[] = ($podcast['outline'][0]);
        }
    }

// OPML file is not in date order.
// Sort into date order based on a nested element
// from https://stackoverflow.com/questions/22416831/php-nested-array-sorting
usort($finalarray, function ($a, $b) {
    return $b['@attributes']['userUpdatedDate'] < $a['@attributes']['userUpdatedDate'];
});

//Remove duplicates, renumber keys, reverse order,
    $uniquearray = array_reverse(array_values(array_unique($finalarray, $sort_flags = SORT_REGULAR))); //SORT_REGULAR means strict type, for date

// Store the $uniquearray of episodes
// These include episodes in progress and deleted episodes.
// We get rid of those later.
file_put_contents($new_episodesfile, json_encode($uniquearray));

// include 'new_episodes.json'
    // Again, as we have just saved $uniquearray as new_episodes.json,
    // it ought to be possible to do without this section, but not right now.
if (file_exists($new_episodesfile)) {
    $newfile = file_get_contents($new_episodesfile); // read file into string
    $new_episodes = json_decode($newfile, true); //
    copy($old_episodesfile, $bak_episodesfile); // housekeeping
    copy($new_episodesfile, $old_episodesfile);
} else {
    exit("Failed to open $new_episodesfile because it does not exist" );
}

// Assemble array of new episodes into $shiny
// That is, not in progress, not deleted.
$shiny = array();
foreach ($new_episodes as $episode) {
    if (!array_key_exists('userDeleted', $episode['@attributes'])) {
        if (!array_key_exists('progress', $episode['@attributes'])) {
            $shiny[] = $episode;
        }
    }
}

// Store the $shiny array of newly completed episodes
file_put_contents($shinyfile, json_encode($shiny));

$shiny = array_reverse($shiny); // Most recent first

// Create a post for each element in $shiny
foreach ($shiny as $episode) { // loop through the new episodes
    // Prepare the slug -- this is my format; yours may differ
    $mydate = date_parse_from_format("Y-m-d+", $episode['@attributes']['userUpdatedDate']);
    $slug = $mydate['year'].'-'.$mydate['month'].'-'.$mydate['day'];

    // Create a folder to receive the files
    // $path from config.php
    $fn = create_folder($path, $slug);
    $myPath = pathinfo($fn, PATHINFO_DIRNAME);

    // Get the summary
    $summary = get_summary($episode['@attributes']['overcastUrl']);

    // get the image
    $myimage = get_image($episode['@attributes']['overcastUrl']);
    file_put_contents($myPath . '/tmp', $myimage);
    $url = $myPath . '/tmp';
    $url_out = $myPath . '/artwork-resized';

    // resize the image
    // This size suits my layout; yours may differ
    $myimage = resizeImage($url, 150, 150, $url_out, $keep_ratio = true);

    // Create the front matter
    $thedate = date('d-m-Y H:i', strtotime($episode['@attributes']['userUpdatedDate']));
    $yaml = build_content($episode['@attributes']['title'], $thedate, $episode['@attributes']['enclosureUrl'], $myimage);

    // Gather it all together and save to file
    $content = $yaml . "Episode summary: " . $summary . "\n";
    write_file($content, $fn);
}

function get_summary($targeturl)
{
    $content = file_get_contents($targeturl);
    if ($content === false) {
        echo "could not get contents from $targeturl for summary";
        die;
    }

    // Get the summary
    // Matches the source code providede by Overcast
    $target = 'og:description" content="';
    $start = strpos($content, $target);
    $end = strpos($content, "\" />", $start);
    $length = $start+strlen($target);

    if ($end != $length) {
        $summary = substr($content, $length, $end-$length);
    } else {
        $summary = ""; // This does happen!
    }
    return html_entity_decode($summary);
}

function create_folder($path, $slug)
{
    $folderPath = $basePath = $path . $slug;
    $counter=0;
    while (file_exists($folderPath)) { // If listen to >1 in a day
        $counter++;
        $folderPath = $basePath."-".$counter;
    }
    mkdir($folderPath); //Creates the folder
    $fn = $folderPath . '/listen.md'; //Sets the filename
return $fn;
}

function get_image($targeturl)
{
    $content = file_get_contents($targeturl);
    if ($content === false) {
        echo "could not get contents from $targeturl for image";
        die;
    }

    //Get the image
    // As with summary, matching source code from Overcast
    // Ideally, I suppose one might have a cache of images and call from that.
    $target = "<img class=\"art fullart\" src=\"";
    $start = strpos($content, $target);
    $end = strpos($content, "\"/>", $start);
    $length = $start+strlen($target);

    if ($end != $length) {
        $imageUrl = substr($content, $length, $end-$length);
    } else {
        $imageUrl = ""; // Not actually sure how best to handle this.
    }

    $image = file_get_contents($imageUrl);
    return($image);
}

// Function modified from https://stackoverflow.com/questions/22868051/create-thumbnails-fixed-sizes-php
function resizeImage($url, $width, $height, $url_out, $keep_ratio = true)
{
    if ($height <= 0 && $width <= 0) {
        return false;
    } else {
        copy($url, $url_out); // just copies

        $info = getimagesize($url);

        // Initialise
        $image = '';
        $final_width = 150; // setting these here, could remove from function call
        $final_height = 150;

        // calculate new sizes. Needed for resize
        list($width_old, $height_old) = $info;

        // Determine image type of original image
        // Make new image based on copy
        switch ($info[2]) {
case IMAGETYPE_GIF:
$image = imagecreatefromgif($url_out);
break;
case IMAGETYPE_JPEG:
$image = imagecreatefromjpeg($url_out);
break;
case IMAGETYPE_PNG:
$image = imagecreatefrompng($url_out);
break;
default:
return false;
}

        // Prepare transparency based on the image type of original image
        $image_resized = imagecreatetruecolor($final_width, $final_height);

        if ($info[2] == IMAGETYPE_GIF || $info[2] == IMAGETYPE_PNG) { // either GIF or PNG
            $transparency = imagecolortransparent($image);
            if ($transparency >= 0) {
                $transparent_color = imagecolorsforindex($image, $trnprt_indx);
                $transparency = imagecolorallocate($image_resized, $trnprt_color['red'], $trnprt_color['green'], $trnprt_color['blue']);
                imagefill($image_resized, 0, 0, $transparency);
                imagecolortransparent($image_resized, $transparency);
            } elseif ($info[2] == IMAGETYPE_PNG) { // in addition to the preceding
                imagealphablending($image_resized, false);
                $color = imagecolorallocatealpha($image_resized, 0, 0, 0, 127);
                imagefill($image_resized, 0, 0, $color);
                imagesavealpha($image_resized, true);
            }
        }

        // Now resize the image
        imagecopyresampled($image_resized, $image, 0, 0, 0, 0, $final_width, $final_height, $width_old, $height_old);
        switch ($info[2]) {
case IMAGETYPE_GIF:
imagegif($image_resized, $url_out.".gif");
$theimage = pathinfo($url_out, PATHINFO_FILENAME).".gif";
break;
case IMAGETYPE_JPEG:
imagejpeg($image_resized, $url_out.".jpg");
$theimage = pathinfo($url_out, PATHINFO_FILENAME).".jpg";
break;
case IMAGETYPE_PNG:
imagepng($image_resized, $url_out.".png");
$theimage = pathinfo($url_out, PATHINFO_FILENAME).".png";
break;
default:
return false;
}

        imagedestroy($image_resized); // Release memory
        unlink($url);
        unlink($url_out);
        return($theimage);
        return(true);
    }
}

function build_content($title, $mydate, $theUrl, $theimage)
{
    $yaml = "---\n";
    $yaml = $yaml . 'title: '. '"&#127911; ' . $title . '"' . "\n";
    $yaml = $yaml .
"published: true
date: $mydate
taxonomy:
    category:
        - stream
    tag:
        - podcasts
header_image: '0'
theurl: $theUrl
image: $theimage
template: item
--- \n";
    return($yaml);
}

function write_file($content, $fn)
{
    file_put_contents($fn, $content, FILE_APPEND | LOCK_EX);
}
