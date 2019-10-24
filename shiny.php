<?

/*
* Just use the stuff stored in shiny.txt to create new posts
* Kind of a failsafe
*
*
*
*
*/

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Get variables from config
$configs = include 'config.php';
$siteUrl = $configs->siteUrl;
date_default_timezone_set($configs->timezone); // Not sure I need this any more, as not doing date comparisons
$path = $configs->notePath; //includes name of parent folder at end
$path = dirname(__DIR__) . $path; // This gets the path of the PARENT

$newfile =  __DIR__ . '/shiny.txt';

if(file_exists($newfile)) {
$shinystring = file_get_contents($newfile); // read file into string
$shiny = json_decode($shinystring, true); //
} else {
    exit('Failed to open $newfile');
}

// var_dump($shiny);

foreach ($shiny as $episode) { // loop through the new episodes
// Prepare the slug

$mydate = date_parse_from_format("Y-m-d+", $episode['@attributes']['userUpdatedDate']);
$slug = $mydate['year'].'-'.$mydate['month'].'-'.$mydate['day'];

// Create a folder to receive the files
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
$myimage = resizeImage($url, 150, 150, $url_out, $keep_ratio = true);

// Create the front matter
$thedate = date('d-m-Y H:i', strtotime($episode['@attributes']['userUpdatedDate']));
$yaml = build_content($episode['@attributes']['title'], $thedate, $episode['@attributes']['overcastUrl'], $myimage);

// Gather it all together and save to file
$content = $yaml . "Episode summary: " . $summary . "\n";
write_file($content,$fn);
}


function get_summary($targeturl)
{
$content = file_get_contents($targeturl);
if ($content === false) {
echo 'could not get file';
die;
}

//Get the summary
$target = 'og:description" content="';
$start = strpos($content, $target);
$end = strpos($content, "\" />", $start);
$length = $start+strlen($target);

if ($end != $length) {
$summary = substr($content, $length, $end-$length);
} else {
$summary = "";
}
return html_entity_decode($summary);
}

function create_folder($path, $slug)
{
$folderPath = $basePath = $path . $slug;
$counter=0;
while (file_exists($folderPath)){
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
echo 'could not get file';
die;
}

//Get the image
$target = "<img class=\"art fullart\" src=\"";
$start = strpos($content, $target);
$end = strpos($content, "\"/>", $start);
$length = $start+strlen($target);

if ($end != $length) {
$imageUrl = substr($content, $length, $end-$length);
} else {
$imageUrl = "";
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
Unlink($url_out);
return($theimage);
Return(TRUE);
}
}

function build_content($title,$mydate, $theUrl, $theimage)
{
$yaml = "---\n";
$yaml = $yaml . 'title: '. '"Listened to: ' . $title . '"' . "\n";
$yaml = $yaml .
"published: true
date: $mydate
taxonomy:
category:
	- stream
tag:
	- podcasts
summary:
enabled: '0'
header_image: '0'
theurl: $theUrl
theimage: $theimage
--- \n";
return($yaml);
}

function write_file($content, $fn)
{
file_put_contents($fn, $content, FILE_APPEND | LOCK_EX);
}
