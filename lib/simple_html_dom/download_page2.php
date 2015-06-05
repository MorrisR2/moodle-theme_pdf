<?php

$pageurl = 'http://www.slashdot.org/';
ini_set('display_errors', 'on');
error_reporting(2039);

include_once('simple_html_dom.php');
include_once('http_build_url.php');

$tmppath = 'tmp';
// $html = file_get_html($pageurl);

$html = str_get_html(simple_curl_string($pageurl));

$pageurlparts = parse_url($pageurl);

$tmppath = $tmppath . '/' . md5($url);
if(!is_dir($tmppath)) {
    mkdir($tmppath);
}

foreach($html->find('img') as $element) {
    $element->src = savereq($tmppath, cleanurl($pageurlparts, $element->src));
}
// <link rel="stylesheet" type="text/css" media="screen, projection" href="//a.fsdn.com/sd/classic.css?release_20150602.01" >
foreach($html->find('link[rel=text/css]') as $element) {
    $element->href = savereq($tmppath, cleanurl($pageurlparts, $element->href));
}
$html->save('result.htm');

function savereq($basepath, $url) {
    if (empty($url)) {
        return '';
    }
   
    $filename = glob_single($basepath, md5($url));

    if (empty($filename)) {
        $stub = $basepath . '/' . md5($url);
        $content_type = simple_curl_content_type($url, $stub);
        $extension = type_to_extension($content_type);
        if (empty($extension)) {
            $extension = pathinfo(parse_url($url,PHP_URL_PATH), PATHINFO_EXTENSION);
        }
        $filename = $stub . $extension;
        if ($filename !== $stub) {
            rename($stub, $filename);
        }
    }
    return 'file://' . realpath($filename);
}


function simple_curl_content_type($url, $filename) {
    $fp = fopen($filename, 'wb');
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_BINARYTRANSFER,1);
    // curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_URL, $url);
    $content = curl_exec($ch);
    fwrite($fp, $content);
    fclose($fp);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close ($ch);
    return $contentType;
}


function simple_curl_string($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_BINARYTRANSFER,1);
    curl_setopt($ch, CURLOPT_URL, $url);
    $content = curl_exec($ch);
    curl_close ($ch);
    return $content;
}


function type_to_extension($type) {
    static $type_to_extension = array (
        'image/gif'	=>	'.gif',
        'image/jp2'	=>	'.jpg2',
        'image/jpeg'	=>	'.jpeg',
        'image/png'	=>	'.png',
        'image/tiff'	=>	'.tiff',
        'image/vnd.adobe.photoshop'	=>	'.psd',
        'image/vnd.djvu'	=>	'.djvu',
        'image/vnd.dxf'	=>	'.dxf',
        'image/vnd.microsoft.icon'	=>	'.ico',
        'image/vnd.sealed.png'	=>	'.spng',
        'image/vnd.sealedmedia.softseal.gif'	=>	'.sgif',
        'image/vnd.sealedmedia.softseal.jpg'	=>	'.sjpg',
        'image/vnd.svf' =>  '.wbmp',
        'image/vnd.wap.wbmp'	=>	'.wbmp',
        'image/vnd.xiff'	=>	'.xif',
        'image/bmp'	=>	'.bmp',
        'image/svg+xml'	=>	'.svg',
        'image/x-cmu-raster'	=>	'.ras',
        'image/x-portable-anymap'	=>	'.pnm',
        'image/x-portable-bitmap'	=>	'.pbm',
        'image/x-portable-graymap'	=>	'.pgm',
        'image/x-portable-pixmap'	=>	'.ppm',
        'image/x-rgb'	=>	'.rgb',
        'image/x-targa'	=>	'.tga',
        'image/x-xbitmap'	=>	'.xbm',
        'image/x-xpixmap'	=>	'.xpm'
    );
    if (!empty($type_to_extension[$type])) {
        return $type_to_extension[$type];
    } else {
        return '';
    }
}

function cleanurl($pageurlparts, $url) {
    if (strpos($url, '//') == 0) {
        return $pageurlparts['scheme'] . ":$url";
    } elseif (strpos($url, 'http') == 0) {
        return $url;
    } else {
        return http_build_url($url, $pageurlparts, HTTP_URL_JOIN_PATH);
    }
    /*
    } elseif (strpos('/') == 1) {
        $url = $pageurlparts['scheme'] . $pageurlparts['host'] . $url;
   } else {
       $url = $pageurlparts['scheme'] . $pageurlparts['host'] . $pageurlparts['path'] . $url;
   }
    */
}


function glob_single($dir, $filestub) {
    if(is_dir($dir)){
        if($dh=opendir($dir)){
            while(($file = readdir($dh)) !== false){
                if(fnmatch($pattern, $file)) {
                    closedir($dh);
                    return "$dir/$file";
                }
            }
            closedir($dh);
        }
    } else {
        throw new Exception("'$dir' is not a directory");
    }
    return false;
}

