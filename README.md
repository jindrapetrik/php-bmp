# php-bmp
Bitmap image support for PHP

This started because in the past there was no support for generating/reading Bitmap (BMP) files in PHP.

PHP has built-in function `imagecreatefrombmp` and `imagebmp` since version 7.2,
but as of my testing - not all BMP file formats are supported.

This project contains own implementation of `imagecreatefrombmp` and `imagebmp` functions.

## Usage

```php
//include lib first
require_once 'bmp.php';

//for PHP before 7.2
$img = imagecreatefrombmp("file.bmp");
//...
header("Content-type: image/bmp");
imagebmp($img);

//for PHP 7.2 and newer (if you want to use new BMP file formats)
use Com\Jpexs\Image\Bitmap;
$bmp = new Bitmap();
$img = $bmp->imageCreateFromBmp("file.bmp");
//...
header("Content-type: image/bmp");
$bmp->imageBmp($img);

```

## Supported file formats
BI_RGB, BI_RLE8, BI_RLE4, BI_BITFIELDS

## Author
Jindra Petřík aka JPEXS

## License
The library is licensed under GNU/LGPL v3
