<?php
// App\Helpers\ImageOptimizer — resizes/recompresses uploads in place.
// Added alongside the fix for package gallery images reaching 9MB
// straight from a phone camera with zero processing. Uses a scratch file
// under the system temp dir, not the real assets/ tree.

use App\Helpers\ImageOptimizer;

function imageOptimizerTestFile(int $width, int $height): string
{
    $path = sys_get_temp_dir() . '/szv_test_image_' . uniqid() . '.jpg';
    $im   = imagecreatetruecolor($width, $height);
    imagefill($im, 0, 0, imagecolorallocate($im, 120, 90, 60));
    imagejpeg($im, $path, 95);
    imagedestroy($im);
    return $path;
}

test('optimize() downscales an oversized image to the max dimension', function () {
    $path = imageOptimizerTestFile(4000, 3000);
    $ok   = ImageOptimizer::optimize($path, 'image/jpeg');
    assertTrue($ok);

    $size = getimagesize($path);
    assertTrue($size[0] <= 1920 && $size[1] <= 1920);
    // Aspect ratio preserved (4:3).
    assertTrue(abs(($size[0] / $size[1]) - (4000 / 3000)) < 0.01);

    unlink($path);
});

test('optimize() leaves an already-small image\'s dimensions untouched', function () {
    $path = imageOptimizerTestFile(400, 300);
    ImageOptimizer::optimize($path, 'image/jpeg');

    $size = getimagesize($path);
    assertSame(400, $size[0]);
    assertSame(300, $size[1]);

    unlink($path);
});

test('optimize() returns false for a non-image file instead of throwing', function () {
    $path = sys_get_temp_dir() . '/szv_test_notimage_' . uniqid() . '.jpg';
    file_put_contents($path, 'this is not an image');

    $ok = ImageOptimizer::optimize($path, 'image/jpeg');
    assertFalse($ok);

    unlink($path);
});
