<?php
function imageCreateFromAny($filepath) {
    $type = exif_imagetype($filepath);
    $allowedTypes = array(
        1,  // [] IMAGETYPE_GIF 
        2,  // [] IMAGETYPE_JPEG 
        3,  // [] IMAGETYPE_PNG 
        6   // [] IMAGETYPE_BMP 
    );
    if (!in_array($type, $allowedTypes)) {
        return false;
    }
    switch ($type) {
        case 1:
            $im = imageCreateFromGif($filepath);
            break;
        case 2:
            $im = imageCreateFromJpeg($filepath);
            break;
        case 3:
            $im = imageCreateFromPng($filepath);
            break;
        case 6:
            $im = imageCreateFromBmp($filepath);
            break;
    }   
    return $im; 
}

$input_image_path = "imgsat/" . $_GET['file'];
$output_image_path = "p1_1/" . $_GET['file'];

$image = imageCreateFromAny($input_image_path);
if ($image === false) {
    die("O formato da imagem não é suportado.");
}
$width = imagesx($image);
$height = imagesy($image);

$a_max=47.005555555556;
$a_min=2.89;
$a_med=18.784917269448;
$b_max=123.40166666667;
$b_min=63.725555555556;
$b_med=96.4389661949685;

$div_a=($a_max-$a_med)/2+$a_med;
$div_b=($b_med-$b_min)/2+$b_min;

$segmentWidth = 4;
$segmentHeight = 4;

$subdWidth = $width/$segmentWidth;
$subdHeight = $height/$segmentHeight;

$output = imagecreatetruecolor($width, $height);
imagesavealpha($output, true);
$transparent = imagecolorallocatealpha($output, 0, 0, 0, 127);
imagefill($output, 0, 0, $transparent);

$colB = imagecolorallocatealpha($output, 255, 255, 0, 63); // alpha = 0.3 * 127
$colA = imagecolorallocatealpha($output, 0, 255, 0, 63); // alpha = 0.3 * 127

for ($x = 0; $x < $subdWidth; $x++) {
    for ($y = 0; $y < $subdHeight; $y++) {

        $sumRed = 0;
        $pixelCount = 0;

        for ($xi = 0; $xi < $segmentWidth; $xi++) {
            for ($yi = 0; $yi < $segmentHeight; $yi++) {
                $pixel = imagecolorat($image, $x * $segmentWidth + $xi, $y * $segmentHeight + $yi);
                $reds = ($pixel >> 16) & 0xFF;

                $sumRed += $reds;
                $pixelCount++;
            }
        }

        $averageRed = $sumRed / $pixelCount;

        if ($averageRed >= $div_b) {
            $colorToFill = $colB;
        } elseif ($averageRed <= $div_a) {
            $colorToFill = $colA;
        } else {
            // Calculate the percentage for gradient
            $percentB = ($averageRed - $div_a) / ($div_b - $div_a);
            $percentA = 1 - $percentB;
        
            // Extract the RGB values for yellow
            $rB = ($colB >> 16) & 0xFF;
            $gB = ($colB >> 8) & 0xFF;
            $bB = $colB & 0xFF;
        
            // Extract the RGB values for green
            $rA = ($colA >> 16) & 0xFF;
            $gA = ($colA >> 8) & 0xFF;
            $bA = $colA & 0xFF;
        
            // Calculate the new RGB values based on the percentages
            $r = ($rB * $percentB) + ($rA * $percentA);
            $g = ($gB * $percentB) + ($gA * $percentA);
            $b = ($bB * $percentB) + ($bA * $percentA);
        
            $colorToFill = imagecolorallocatealpha($output, $r, $g, $b, 63); // 63 alpha for 0.3 transparency
        }
        imagefilledrectangle($output, $x * $segmentWidth, $y * $segmentHeight, ($x+1) * $segmentWidth, ($y+1) * $segmentHeight, $colorToFill);
    }
}

imagepng($output, $output_image_path);
imagedestroy($image);
imagedestroy($output);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Image Processor</title>
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background-color: #f5f5f5;
        }
        .image-container {
            position: relative;
        }
        .image-container img.overlay {
            position: absolute;
            top: 0;
            left: 0;
        }
    </style>
</head>
<body>

<div class="image-container">
    <img src="<?= $input_image_path ?>" alt="Original Image">
    <img src="<?= $output_image_path ?>" class="overlay" alt="Processed Image">
</div>

</body>
</html>
