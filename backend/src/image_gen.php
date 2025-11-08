<?php
// src/image_gen.php
// Provides function generate_message_image($text, $palette, $outputPath)
// palette expected as array e.g. ['bg'=>'#5b6cff','text'=>'#ffffff'] or gradient string

function generate_message_image($text, $palette, $outputPath){
    // Try Imagick first
    if(class_exists('Imagick')){
        $im = new Imagick();
        $width = 1080; $height = 1080;
        $bg = is_array($palette) && !empty($palette['bg']) ? $palette['bg'] : '#5b6cff';
        // create canvas
        $canvas = new Imagick();
        $canvas->newImage($width, $height, new ImagickPixel($bg));
        $draw = new ImagickDraw();
        $draw->setFillColor(isset($palette['text']) ? $palette['text'] : '#ffffff');
        $draw->setFontSize(36);
        // use a system font; production: bundle ttf and point to it
        $draw->setFont('/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf');
        // simple word wrap
        $lines = wrapTextImagick($draw, $text, $width - 160);
        $y = 140;
        foreach($lines as $line){
            $canvas->annotateImage($draw, 80, $y, 0, $line);
            $y += 60;
        }
        $canvas->setImageFormat('png');
        $canvas->writeImage($outputPath);
        $canvas->clear(); $canvas->destroy();
        return file_exists($outputPath);
    }

    // Fallback to GD
    if(function_exists('imagecreatetruecolor')){
        $width = 1080; $height = 1080;
        $im = imagecreatetruecolor($width, $height);
        // parse bg color
        $bg = is_array($palette) && !empty($palette['bg']) ? $palette['bg'] : '#5b6cff';
        $textc = isset($palette['text']) ? $palette['text'] : '#ffffff';
        list($r,$g,$b) = hexToRgb($bg);
        $bgcol = imagecolorallocate($im, $r,$g,$b);
        imagefilledrectangle($im, 0,0,$width,$height, $bgcol);
        // text color
        list($tr,$tg,$tb) = hexToRgb($textc);
        $tcol = imagecolorallocate($im, $tr,$tg,$tb);
        $font = __DIR__ . '/fonts/DejaVuSans.ttf'; // ensure font exists
        if(!file_exists($font)){
            // use system fallback
            $font = null;
        }
        $fontSize = 22;
        // basic wrap
        $x = 80; $y = 140;
        $maxw = $width - 160;
        $words = explode(' ', $text);
        $line = '';
        foreach($words as $w){
            $test = trim($line . ' ' . $w);
            if($font){
                $bbox = imagettfbbox($fontSize, 0, $font, $test);
                $wtest = $bbox ? ($bbox[2]-$bbox[0]) : strlen($test) * 12;
            } else {
                $wtest = strlen($test) * 10;
            }
            if($wtest > $maxw && $line){
                if($font) imagettftext($im, $fontSize, 0, $x, $y, $tcol, $font, $line);
                else imagestring($im, 5, $x, $y, $line, $tcol);
                $line = $w;
                $y += 40;
            } else {
                $line = $test;
            }
        }
        if($line){
            if($font) imagettftext($im, $fontSize, 0, $x, $y, $tcol, $font, $line);
            else imagestring($im, 5, $x, $y, $line, $tcol);
        }
        // save
        imagepng($im, $outputPath);
        imagedestroy($im);
        return file_exists($outputPath);
    }

    return false;
}

// helper to wrap for Imagick (approx)
function wrapTextImagick(ImagickDraw $draw, $text, $maxWidth){
    $words = preg_split('/\s+/', $text);
    $lines = []; $line = '';
    $canvas = new Imagick();
    foreach($words as $w){
        $test = trim($line . ' ' . $w);
        $metrics = $canvas->queryFontMetrics($draw, $test);
        if($metrics['textWidth'] > $maxWidth && $line){
            $lines[] = $line;
            $line = $w;
        } else {
            $line = $test;
        }
    }
    if($line) $lines[] = $line;
    return $lines;
}

function hexToRgb($hex){
    $hex = str_replace('#','', $hex);
    if(strlen($hex) === 3){
        $r = hexdec(str_repeat(substr($hex,0,1),2));
        $g = hexdec(str_repeat(substr($hex,1,1),2));
        $b = hexdec(str_repeat(substr($hex,2,1),2));
    } else {
        $r = hexdec(substr($hex,0,2));
        $g = hexdec(substr($hex,2,2));
        $b = hexdec(substr($hex,4,2));
    }
    return [$r,$g,$b];
}

// Remarque : pour GD fallback, place un fichier de police TTF dans src/fonts/DejaVuSans.ttf (ou modifie le chemin). Imagick version utilise /usr/share/fonts/... par défaut — en prod tu définis une police bundlée.