<?php

// ---------------------------------------------------------
// CONFIGURATION
// ---------------------------------------------------------

$sourceDir = '../unprocessed_photos';
$outputDir = 'photography/';

$watermarkFile = 'img/lj.png';

// Maximum size of the generated image.
// 2400px is generally plenty for a portfolio.
$maxDimension = 2400;

// WebP quality: 0-100
$quality = 85;

// Watermark width as a percentage of the photo width.
$watermarkPercentage = 22;

// Watermark opacity: 0-100
$watermarkOpacity = 55;

// Position:
// "bottom-right", "bottom-left", "top-right", "top-left", "center"
$watermarkPosition = 'bottom-right';

// Space between watermark and edge.
$margin = 40;


// ---------------------------------------------------------
// CHECK REQUIREMENTS
// ---------------------------------------------------------

if (!extension_loaded('gd')) {
    die("ERROR: PHP GD extension is not installed.\n");
}

if (!is_dir($sourceDir)) {
    die("ERROR: Source directory does not exist:\n$sourceDir\n");
}

if (!file_exists($watermarkFile)) {
    die("ERROR: Watermark file does not exist:\n$watermarkFile\n");
}

if (!is_dir($outputDir)) {
    if (!mkdir($outputDir, 0755, true)) {
        die("ERROR: Could not create output directory.\n");
    }
}


// ---------------------------------------------------------
// LOAD WATERMARK
// ---------------------------------------------------------

$watermark = imagecreatefrompng($watermarkFile);

if (!$watermark) {
    die("ERROR: Could not load watermark PNG.\n");
}

imagealphablending($watermark, true);
imagesavealpha($watermark, true);


// ---------------------------------------------------------
// FIND ALL PHOTOS
// ---------------------------------------------------------

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(
        $sourceDir,
        FilesystemIterator::SKIP_DOTS
    )
);

$count = 0;
$processed = 0;
$skipped = 0;
$errors = 0;

foreach ($iterator as $file) {

    if (!$file->isFile()) {
        continue;
    }

    $extension = strtolower($file->getExtension());

    if (!in_array($extension, ['jpg', 'jpeg', 'png'])) {
        continue;
    }

    $count++;

    $sourcePath = $file->getPathname();

    // Get path relative to source directory
    $relativePath = substr(
        $sourcePath,
        strlen($sourceDir) + 1
    );

    // Remove extension
    $relativeWithoutExtension = pathinfo(
        $relativePath,
        PATHINFO_DIRNAME
    );

    $filename = pathinfo(
        $relativePath,
        PATHINFO_FILENAME
    );

    if ($relativeWithoutExtension === '.') {
        $relativeWithoutExtension = '';
    }

    // Output directory
    $destinationDirectory = $outputDir;

    if ($relativeWithoutExtension !== '') {
        $destinationDirectory .= '/' . $relativeWithoutExtension;
    }

    if (!is_dir($destinationDirectory)) {
        mkdir($destinationDirectory, 0755, true);
    }

    // Output filename
    $destinationPath =
        $destinationDirectory .
        '/' .
        $filename .
        '.webp';


    // -----------------------------------------------------
    // DON'T PROCESS ALREADY GENERATED FILES
    // -----------------------------------------------------

    if (file_exists($destinationPath)) {

        echo "SKIP: $relativePath\n";

        $skipped++;

        continue;
    }


    // -----------------------------------------------------
    // LOAD IMAGE
    // -----------------------------------------------------

    switch ($extension) {

        case 'jpg':
        case 'jpeg':
            $image = @imagecreatefromjpeg($sourcePath);
            break;

        case 'png':
            $image = @imagecreatefrompng($sourcePath);
            break;

        default:
            $image = false;
    }

    if (!$image) {

        echo "ERROR: Could not read $relativePath\n";

        $errors++;

        continue;
    }


    // -----------------------------------------------------
    // FIX JPEG EXIF ORIENTATION
    // -----------------------------------------------------

    if (
        in_array($extension, ['jpg', 'jpeg']) &&
        function_exists('exif_read_data')
    ) {

        $exif = @exif_read_data($sourcePath);

        if (!empty($exif['Orientation'])) {

            switch ($exif['Orientation']) {

                case 3:
                    $image = imagerotate($image, 180, 0);
                    break;

                case 6:
                    $image = imagerotate($image, -90, 0);
                    break;

                case 8:
                    $image = imagerotate($image, 90, 0);
                    break;
            }
        }
    }


    // -----------------------------------------------------
    // GET ORIGINAL DIMENSIONS
    // -----------------------------------------------------

    $originalWidth = imagesx($image);
    $originalHeight = imagesy($image);


    // -----------------------------------------------------
    // CALCULATE NEW SIZE
    // -----------------------------------------------------

    $scale = min(
        1,
        $maxDimension / max($originalWidth, $originalHeight)
    );

    $newWidth = (int) round($originalWidth * $scale);
    $newHeight = (int) round($originalHeight * $scale);


    // -----------------------------------------------------
    // RESIZE
    // -----------------------------------------------------

    $resized = imagecreatetruecolor(
        $newWidth,
        $newHeight
    );

    // White background.
    // This is useful if PNGs have transparent areas.
    $white = imagecolorallocate(
        $resized,
        255,
        255,
        255
    );

    imagefill(
        $resized,
        0,
        0,
        $white
    );

    imagecopyresampled(
        $resized,
        $image,
        0,
        0,
        0,
        0,
        $newWidth,
        $newHeight,
        $originalWidth,
        $originalHeight
    );

    imagedestroy($image);

    $image = $resized;


    // -----------------------------------------------------
    // CREATE WATERMARK
    // -----------------------------------------------------

    $watermarkOriginalWidth = imagesx($watermark);
    $watermarkOriginalHeight = imagesy($watermark);

    $watermarkWidth = (int) round(
        $newWidth * ($watermarkPercentage / 100)
    );

    $watermarkHeight = (int) round(
        $watermarkOriginalHeight *
        ($watermarkWidth / $watermarkOriginalWidth)
    );


    // Resize watermark
    $watermarkResized = imagecreatetruecolor(
        $watermarkWidth,
        $watermarkHeight
    );

    imagealphablending(
        $watermarkResized,
        false
    );

    imagesavealpha(
        $watermarkResized,
        true
    );

    $transparent = imagecolorallocatealpha(
        $watermarkResized,
        0,
        0,
        0,
        127
    );

    imagefill(
        $watermarkResized,
        0,
        0,
        $transparent
    );

    imagecopyresampled(
        $watermarkResized,
        $watermark,
        0,
        0,
        0,
        0,
        $watermarkWidth,
        $watermarkHeight,
        $watermarkOriginalWidth,
        $watermarkOriginalHeight
    );


    // -----------------------------------------------------
    // WATERMARK POSITION
    // -----------------------------------------------------

    switch ($watermarkPosition) {

        case 'top-left':

            $x = $margin;
            $y = $margin;

            break;

        case 'top-right':

            $x = $newWidth -
                 $watermarkWidth -
                 $margin;

            $y = $margin;

            break;

        case 'bottom-left':

            $x = $margin;

            $y = $newHeight -
                 $watermarkHeight -
                 $margin;

            break;

        case 'center':

            $x = (int) (
                ($newWidth - $watermarkWidth) / 2
            );

            $y = (int) (
                ($newHeight - $watermarkHeight) / 2
            );

            break;

        case 'bottom-right':
        default:

            $x = $newWidth -
                 $watermarkWidth -
                 $margin;

            $y = $newHeight -
                 $watermarkHeight -
                 $margin;

            break;
    }


    // -----------------------------------------------------
    // APPLY WATERMARK
    // -----------------------------------------------------

    imagecopymerge(
        $image,
        $watermarkResized,
        $x,
        $y,
        0,
        0,
        $watermarkWidth,
        $watermarkHeight,
        $watermarkOpacity
    );

    imagedestroy($watermarkResized);


    // -----------------------------------------------------
    // SAVE WEBP
    // -----------------------------------------------------

    if (!imagewebp(
        $image,
        $destinationPath,
        $quality
    )) {

        echo "ERROR: Could not save $relativePath\n";

        $errors++;

    } else {

        echo "OK: $relativePath\n";

        $processed++;
    }

    imagedestroy($image);
}


// ---------------------------------------------------------
// CLEANUP
// ---------------------------------------------------------

imagedestroy($watermark);


// ---------------------------------------------------------
// SUMMARY
// ---------------------------------------------------------

echo "\n";
echo "----------------------------------------\n";
echo "Finished\n";
echo "----------------------------------------\n";
echo "Photos found:  $count\n";
echo "Processed:     $processed\n";
echo "Skipped:       $skipped\n";
echo "Errors:        $errors\n";
echo "----------------------------------------\n";
