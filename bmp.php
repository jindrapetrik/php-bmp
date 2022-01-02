<?php

/**
 * JPEXS BMP Image functions
 * @version 2.2
 * @author JPEXS
 * @copyright (c) JPEXS 2004-2022
 *
 * Webpage: http://www.jpexs.com
 * Email: jpexs@jpexs.com
 *
 *
 *        Version changes:
 *                2022-01-03 v2.2
 *                      - fixed remainder in 16bit BI_BITFIELDS
 *                2020-04-27 v2.1
 *                      - trigging notice on invalid compression
 *                      - added BI_BITFIELDS compression support
 *                2020-04-01  v2.0
 *                      - code formatting,
 *                      - correctly closing files fix
 *                      - class encapsulation
 *                      - reading 32 bit images
 *                      - ignoring unknown compressions
 *                      - License changed to GNU/LGPL v3
 *                2012-02-18  v1.2 - License changed to GNU/GPL v3
 *                2009-09-23  v1.1
 *                      - redesigned sourcecode,
 *                      - phpdoc included,
 *                      - all internal functions and global variables have prefix "jpexs_"
 *
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 3.0 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.
 */

namespace Com\Jpexs\Image
{
      class Bitmap
      {
            const BI_RGB = 0;
            const BI_RLE8 = 1;
            const BI_RLE4 = 2;
            const BI_BITFIELDS = 3;
            private $currentBit = 0;

            /**
             * Creates new BMP file from image resource
             * @param resource $image Image resource to convert
             * @param string $to File to save image to. If ommited or "", file is written to standard output
             * @param bool $compressed When true, file will be saved with RLE compression (EXPERIMENTAL)
             * @return bool True when successfully writen to specified file
             */
            public function imageBmp($image, $to = "", $compressed = false)
            {
                  $colorCount = imagecolorstotal($image);

                  $transparent = imagecolortransparent($image);
                  $isTransparent = $transparent != -1;


                  if ($isTransparent)
                  {
                        $colorCount--;
                  }

                  if ($colorCount == 0)
                  {
                        $colorCount = 0;
                        $bitCount = 24;
                  }
                  if (($colorCount > 0) and ($colorCount <= 2))
                  {
                        $colorCount = 2;
                        $bitCount = 1;
                  }
                  if (($colorCount > 2) and ($colorCount <= 16))
                  {
                        $colorCount = 16;
                        $bitCount = 4;
                  }
                  if (($colorCount > 16) and ($colorCount <= 256))
                  {
                        $colorCount = 0;
                        $bitCount = 8;
                  }


                  if ($bitCount != 8)
                  {
                        $compressed = false;
                  }

                  $width = imagesx($image);
                  $height = imagesy($image);

                  $remainder = (4 - ($width / (8 / $bitCount)) % 4) % 4;

                  $paletteSize = 0;
                  if ($bitCount < 24)
                  {
                        $paletteSize = pow(2, $bitCount) * 4;
                  }

                  $size = (floor($width / (8 / $bitCount)) + $remainder) * $height + 54;
                  $size += $paletteSize;
                  $offset = 54 + $paletteSize;

                  // Bitmap File Header
                  $ret = 'BM';                        // header (2b)
                  $ret .= $this->int_to_dword($size);        // size of file (4b)
                  $ret .= $this->int_to_dword(0);        // reserved (4b)
                  $ret .= $this->int_to_dword($offset);        // byte location in the file which is first byte of IMAGE (4b)
                  // Bitmap Info Header
                  $ret .= $this->int_to_dword(40);        // Size of BITMAPINFOHEADER (4b)
                  $ret .= $this->int_to_dword($width);        // width of bitmap (4b)
                  $ret .= $this->int_to_dword($height);        // height of bitmap (4b)
                  $ret .= $this->int_to_word(1);        // biPlanes = 1 (2b)
                  $ret .= $this->int_to_word($bitCount);        // biBitCount = {1 (mono) or 4 (16 clr ) or 8 (256 clr) or 24 (16 Mil)} (2b)
                  $ret .= $this->int_to_dword($compressed);        // RLE COMPRESSION (4b)
                  $ret .= $this->int_to_dword(0);        // width x height (4b)
                  $ret .= $this->int_to_dword(0);        // biXPelsPerMeter (4b)
                  $ret .= $this->int_to_dword(0);        // biYPelsPerMeter (4b)
                  $ret .= $this->int_to_dword(0);        // Number of palettes used (4b)
                  $ret .= $this->int_to_dword(0);        // Number of important colour (4b)
                  // image data

                  $CC = $colorCount;
                  if ($CC == 0) $CC = 256;
                  if ($bitCount < 24)
                  {
                        $colorTotal = imagecolorstotal($image);
                        if ($isTransparent) $colorTotal--;

                        for ($p = 0; $p < $colorTotal; $p++)
                        {
                              $color = imagecolorsforindex($image, $p);
                              $ret .= $this->inttobyte($color["blue"]);
                              $ret .= $this->inttobyte($color["green"]);
                              $ret .= $this->inttobyte($color["red"]);
                              $ret .= $this->inttobyte(0); //RESERVED
                        }

                        for ($p = $colorTotal; $p < $CC; $p++)
                        {
                              $ret .= $this->inttobyte(0);
                              $ret .= $this->inttobyte(0);
                              $ret .= $this->inttobyte(0);
                              $ret .= $this->inttobyte(0); //RESERVED
                        }
                  }

                  $retd = "";
                  if ($bitCount <= 8)
                  {

                        for ($y = $height - 1; $y >= 0; $y--)
                        {
                              $bWrite = "";
                              for ($x = 0; $x < $width; $x++)
                              {
                                    $color = imagecolorat($image, $x, $y);
                                    $bWrite .= $this->decbinx($color, $bitCount);
                                    if (strlen($bWrite) == 8)
                                    {
                                          $retd .= $this->inttobyte(bindec($bWrite));
                                          $bWrite = "";
                                    }
                              }

                              if ((strlen($bWrite) < 8) and (strlen($bWrite) != 0))
                              {
                                    $sl = strlen($bWrite);
                                    for ($t = 0; $t < 8 - $sl; $t++)
                                    {
                                          $sl .= "0";
                                    }
                                    $retd .= $this->inttobyte(bindec($bWrite));
                              }
                              for ($z = 0; $z < $remainder; $z++)
                              {
                                    $retd .= $this->inttobyte(0);
                              }
                        }
                  }

                  if (($compressed) && ($bitCount == 8))
                  {
                        for ($t = 0; $t < strlen($retd); $t += 4)
                        {
                              if ($t != 0)
                              {
                                    if (($t) % $width == 0)
                                    {
                                          $ret .= chr(0) . chr(0);
                                    }
                              }
                              if (($t + 5) % $width == 0)
                              {
                                    $ret .= chr(0) . chr(5) . substr($retd, $t, 5) . chr(0);
                                    $t += 1;
                              }
                              if (($t + 6) % $width == 0)
                              {
                                    $ret .= chr(0) . chr(6) . substr($retd, $t, 6);
                                    $t += 2;
                              }
                              else
                              {
                                    $ret .= chr(0) . chr(4) . substr($retd, $t, 4);
                              }
                        }
                        $ret .= chr(0) . chr(1);
                  }
                  else
                  {
                        $ret .= $retd;
                  }


                  if ($bitCount == 24)
                  {
                        $additional = "";
                        for ($z = 0; $z < $remainder; $z++)
                        {
                              $additional .= chr(0);
                        }

                        for ($y = $height - 1; $y >= 0; $y--)
                        {
                              for ($x = 0; $x < $width; $x++)
                              {
                                    $color = imagecolorsforindex($image, ImageColorAt($image, $x, $y));
                                    $ret .= chr($color["blue"]) . chr($color["green"]) . chr($color["red"]);
                              }
                              $ret .= $additional;
                        }

                  }

                  if ($to != "")
                  {
                        $r = ($f = fopen($to, "w"));
                        $r = $r && fwrite($f, $ret);
                        $r = $r && fclose($f);
                        return $r;
                  }
                  echo $ret;
                  return null;
            }

            /**
             * Reads image from a BMP file and converts it to image resource
             * @param string $file File to read BMP image from
             * @return resource|false Image resource or false on error
             *
             * Note:
             *  Reading RLE compressed bitmaps is EXPERIMENTAL
             *  Reading palette based bitmaps with less than 8bit palette is EXPERIMENTAL
             */
            public function imageCreateFromBmp($file)
            {
                  $this->currentBit = 0;
                  $f = fopen($file, "r");
                  $Header = fread($f, 2);
                  if ($Header == "BM")
                  {
                        $this->freaddword($f); //Size
                        $this->freadword($f); //Reserved1
                        $this->freadword($f); //Reserved2
                        $this->freaddword($f); //FirstByteOfImage

                        $this->freaddword($f); //SizeBITMAPINFOHEADER
                        $width = $this->freaddword($f);
                        $height = $this->freaddword($f);
                        $this->freadword($f); //biPlanes
                        $biBitCount = $this->freadword($f);
                        $compressionMethod = $this->freaddword($f);

                        $this->freaddword($f); //WidthxHeight
                        $this->freaddword($f); //biXPelsPerMeter
                        $this->freaddword($f); //biYPelsPerMeter
                        $this->freaddword($f); //NumberOfPalettesUsed
                        $this->freaddword($f); //NumberOfImportantColors

                        $palette = [];

                        $img = false; //default value returned on error

                        if ($biBitCount < 24)
                        {
                              $img = imagecreate($width, $height);
                              $Colors = pow(2, $biBitCount);
                              if ($compressionMethod !== self::BI_BITFIELDS)
                              {
                                    for ($p = 0; $p < $Colors; $p++)
                                    {
                                          $B = $this->freadbyte($f);
                                          $G = $this->freadbyte($f);
                                          $R = $this->freadbyte($f);
                                          $this->freadbyte($f); //Reserved
                                          $palette[] = imagecolorallocate($img, $R, $G, $B);
                                    }
                              }


                              if ($compressionMethod == self::BI_RGB)
                              {
                                    $remainder = (4 - ceil(($width / (8 / $biBitCount))) % 4) % 4;

                                    for ($y = $height - 1; $y >= 0; $y--)
                                    {
                                          $this->currentBit = 0;
                                          for ($x = 0; $x < $width; $x++)
                                          {
                                                $C = $this->freadbits($f, $biBitCount);
                                                imagesetpixel($img, $x, $y, $palette[$C]);
                                          }
                                          if ($this->currentBit != 0)
                                          {
                                                $this->freadbyte($f);
                                          }
                                          for ($g = 0; $g < $remainder; $g++)
                                                $this->freadbyte($f);
                                    }

                              }
                        }


                        if ($compressionMethod == self::BI_RLE8)
                        {
                              $y = $height;

                              $pocetb = 0;

                              $data = "";
                              while (true)
                              {
                                    $y--;
                                    $prefix = $this->freadbyte($f);
                                    $suffix = $this->freadbyte($f);
                                    $pocetb += 2;
                                    if (($prefix == 0) && ($suffix == 1)) break;
                                    if (feof($f)) break;

                                    while (!(($prefix == 0) && ($suffix == 0)))
                                    {
                                          if ($prefix == 0)
                                          {
                                                $pocet = $suffix;
                                                $data .= fread($f, $pocet);
                                                $pocetb += $pocet;
                                                if ($pocetb % 2 == 1)
                                                {
                                                      $this->freadbyte($f);
                                                      $pocetb++;
                                                }
                                          }
                                          if ($prefix > 0)
                                          {
                                                $pocet = $prefix;
                                                for ($r = 0; $r < $pocet; $r++)
                                                      $data .= chr($suffix);
                                          }
                                          $prefix = $this->freadbyte($f);
                                          $suffix = $this->freadbyte($f);
                                          $pocetb += 2;
                                    }

                                    for ($x = 0; $x < strlen($data); $x++)
                                    {
                                          imagesetpixel($img, $x, $y, $palette[ord($data[$x])]);
                                    }
                                    $data = "";

                              }

                        }
                        else if ($compressionMethod == self::BI_RLE4)
                        {
                              $y = $height;
                              $pocetb = 0;

                              $data = "";

                              while (true)
                              {
                                    $y--;
                                    $prefix = $this->freadbyte($f);
                                    $suffix = $this->freadbyte($f);
                                    $pocetb += 2;

                                    if (($prefix == 0) && ($suffix == 1)) break;
                                    if (feof($f)) break;

                                    while (!(($prefix == 0) && ($suffix == 0)))
                                    {
                                          if ($prefix == 0)
                                          {
                                                $pocet = $suffix;

                                                $this->currentBit = 0;
                                                for ($h = 0; $h < $pocet; $h++)
                                                {
                                                      $data .= chr($this->freadbits($f, 4));
                                                }
                                                if ($this->currentBit != 0)
                                                {
                                                      $this->freadbits($f, 4);
                                                }
                                                $pocetb += ceil(($pocet / 2));
                                                if ($pocetb % 2 == 1)
                                                {
                                                      $this->freadbyte($f);
                                                      $pocetb++;
                                                }
                                          }
                                          if ($prefix > 0)
                                          {
                                                $pocet = $prefix;
                                                $i = 0;
                                                for ($r = 0; $r < $pocet; $r++)
                                                {
                                                      if ($i % 2 == 0)
                                                      {
                                                            $data .= chr($suffix % 16);
                                                      }
                                                      else
                                                      {
                                                            $data .= chr(floor($suffix / 16));
                                                      }
                                                      $i++;
                                                }
                                          }
                                          $prefix = $this->freadbyte($f);
                                          $suffix = $this->freadbyte($f);
                                          $pocetb += 2;
                                    }

                                    for ($x = 0; $x < strlen($data); $x++)
                                    {
                                          imagesetpixel($img, $x, $y, $palette[ord($data[$x])]);
                                    }
                                    $data = "";

                              }
                        }
                        else if ($compressionMethod === self::BI_BITFIELDS)
                        {
                              if (!in_array($biBitCount, [16, 32], true))
                              {
                                    //invalid bit count with BI_BITFIELDS compression
                                    trigger_error("imagrecreatefrombmp: Invalid bit count form BI_BITFIELDS compression: " . $biBitCount);

                                    return false;
                              }

                              $redMask = $this->freaddword($f);
                              $greenMask = $this->freaddword($f);
                              $blueMask = $this->freaddword($f);
                              $redZeroes = $this->numBitZeroesFromRight($redMask);
                              $greenZeroes = $this->numBitZeroesFromRight($greenMask);
                              $blueZeroes = $this->numBitZeroesFromRight($blueMask);


                              $img = imagecreatetruecolor($width, $height);
                              $remainder = $biBitCount == 32 ? 0 : (4 - (($width * 2) % 4)) % 4;

                              for ($y = $height - 1; $y >= 0; $y--)
                              {
                                    for ($x = 0; $x < $width; $x++)
                                    {
                                          if ($biBitCount == 16)
                                          {
                                                $w = $this->freadword($f);
                                          }
                                          else //32
                                          {
                                                $w = $this->freaddword($f);
                                          }

                                          $R = floor((($w & $redMask)>>$redZeroes)*255/($redMask>>$redZeroes));
                                          $G = floor((($w & $greenMask)>>$greenZeroes)*255/($greenMask>>$greenZeroes));
                                          $B = floor((($w & $blueMask)>>$blueZeroes)*255/($blueMask>>$blueZeroes));

                                          $color = imagecolorexact($img, $R, $G, $B);
                                          if ($color == -1) $color = imagecolorallocate($img, $R, $G, $B);
                                          imagesetpixel($img, $x, $y, $color);
                                    }
                                    for ($z = 0; $z < $remainder; $z++)
                                    {
                                          $this->freadbyte($f);
                                    }
                              }
                        }
                        else if ($compressionMethod != 0)
                        {
                              //unsupported compression method
                              trigger_error("imagrecreatefrombmp: Unsupported compression method: ".$compressionMethod);
                              return false;
                        }

                        if ($biBitCount == 24 && $compressionMethod === self::BI_RGB)
                        {
                              $img = imagecreatetruecolor($width, $height);
                              $remainder = 4 - (($width*3) % 4);

                              for ($y = $height - 1; $y >= 0; $y--)
                              {
                                    for ($x = 0; $x < $width; $x++)
                                    {
                                          $B = $this->freadbyte($f);
                                          $G = $this->freadbyte($f);
                                          $R = $this->freadbyte($f);
                                          $color = imagecolorexact($img, $R, $G, $B);
                                          if ($color == -1) $color = imagecolorallocate($img, $R, $G, $B);
                                          imagesetpixel($img, $x, $y, $color);
                                    }
                                    for ($z = 0; $z < $remainder; $z++)
                                    {
                                          $this->freadbyte($f);
                                    }
                              }
                        }
                        if ($biBitCount == 32 && $compressionMethod === self::BI_RGB)
                        {
                              $img = imagecreatetruecolor($width,$height);
                              for ($y = $height - 1; $y >= 0; $y--)
                              {
                                    for ($x = 0; $x < $width; $x++)
                                    {
                                          $B = $this->freadbyte($f);
                                          $G = $this->freadbyte($f);
                                          $R = $this->freadbyte($f);
                                          $this->freadbyte($f); //reserved
                                          $color = imagecolorexact($img, $R, $G, $B);
                                          if ($color == -1) $color = imagecolorallocate($img, $R, $G, $B);
                                          imagesetpixel($img, $x, $y, $color);
                                    }
                              }
                        }
                        fclose($f);
                        return $img;

                  }
                  else
                  {
                        fclose($f);
                        return false;
                  }
            }

            private function numBitZeroesFromRight($num)
            {
                  if ($num == 0)
                  {
                        return 1;
                  }
                  $ret = 0;
                  while(($num & 1) === 0)
                  {
                        $ret++;
                        $num = $num >> 1;
                  }
                  return $ret;
            }

            /**
             * reads 1 byte from file
             * @param resource $f File
             * @return int
             */
            private function freadbyte($f)
            {
                  return ord(fread($f, 1));
            }

            /**
             * reads 2 bytes (1 word) from file
             * @param resource $f File
             * @return float|int
             */
            private function freadword($f)
            {
                  $ret = unpack("vvalue", fread($f, 2));
                  return $ret["value"];
            }

            /**
             * reads 4 bytes (1 dword) from file
             * @param resource $f File
             * @return float|int
             */
            private function freaddword($f)
            {
                  $ret = unpack("Vvalue", fread($f, 4));
                  return $ret["value"];
            }

            /**
             * returns bits $start->$start+$len from $byte
             * @param $byte
             * @param $start
             * @param $len
             * @return float|int
             */
            private function retBits($byte, $start, $len)
            {
                  $bin = $this->decbin8($byte);
                  return bindec(substr($bin, $start, $len));
            }


            /**
             * reads next $count bits from file
             * @param $f
             * @param $count
             * @return float|int
             */
            private function freadbits($f, $count)
            {
                  $Byte = $this->freadbyte($f);
                  $LastCBit = $this->currentBit;
                  $this->currentBit += $count;
                  if ($this->currentBit == 8)
                  {
                        $this->currentBit = 0;
                  }
                  else
                  {
                        fseek($f, ftell($f) - 1);
                  }
                  return $this->retBits($Byte, $LastCBit, $count);
            }

            /**
             * returns 4 byte representation of $n
             * @param $n
             * @return string
             */
            private function int_to_dword($n)
            {
                  return pack("V", $n);
            }

            /**
             * returns 2 byte representation of $n
             * @param $n
             * @return string
             */
            private function int_to_word($n)
            {
                  return pack("v", $n);
            }

            /**
             * returns binary string of d zero filled to 8
             * @param $d
             * @return string
             */
            private function decbin8($d)
            {
                  return $this->decbinx($d, 8);
            }

            private function decbinx($d, $n)
            {
                  $bin = decbin($d);
                  $sbin = strlen($bin);
                  for ($j = 0; $j < $n - $sbin; $j++)
                  {
                        $bin = "0$bin";
                  }
                  return $bin;
            }

            private function inttobyte($n)
            {
                  return chr($n);
            }

      }
}

namespace
{

      use Com\Jpexs\Image\Bitmap;

      if (!function_exists("imagecreatefrombmp")) //use PHP7.2 built-in functions when available
      {
            /**
             * Reads image from a BMP file and converts it to image resource
             * @param string $file File to read BMP image from
             * @return resource|false Image resource or false on error
             *
             * Note:
             *  Reading RLE compressed bitmaps is EXPERIMENTAL
             *  Reading palette based bitmaps with less than 8bit palette is EXPERIMENTAL
             */
            function imagecreatefrombmp($file)
            {
                  $bmp = new Bitmap();
                  return $bmp->imageCreateFromBmp($file);
            }
      }

      if (!function_exists("imagebmp"))
      {
            /**
             * Creates new BMP file from image resource
             * @param resource $image Image resource to convert
             * @param string $to File to save image to. If ommited or "", file is written to standard output
             * @param bool $compressed When true, file will be saved with RLE compression (EXPERIMENTAL)
             * @return bool True when successfully writen to specified file
             */
            function imagebmp($image, $to = "", $compressed = false)
            {
                  $bmp = new Bitmap();
                  return $bmp->imageBmp($image, $to, $compressed);
            }
      }
}
