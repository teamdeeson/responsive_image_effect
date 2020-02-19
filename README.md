# 🖼️ Responsive image generator.

This module allows the programatic generation of responsive images.

!Warning: Use of this module *replaces* Drupal's standard mechanism for building image styles.  Any image style you create MUST now include the
new responsive effect.

Given an image file called `test.png` in the public file, i.e. `sites/default/files/test.png`

Then the responsive image style will generate URLs of the type:

https://site.localhost/sites/default/files/styles/responsive/public/{width}/{height}/{crop}/test.png?itok={itok}

Where the parameters are as follows:

* {width} - the width in pixels (e.g. 1024)
* {height} - the height in pixels (e.g. 768)
* {crop} - a 1 or 0 to designate whether to use a crop to force the image to the exact dimensions specified (1 or 0). If set to 0 then the image may be taller or wider than the dimensions specified but nothing will be lost.
* {itok}: Drupal's security token to ensure only URLs generated by Drupal can be used. Without this the server could be Denial of service attacked.

For example, this generates a cropped 400 x 400 image:
https://site.localhost/sites/default/files/styles/responsive/public/400/400/1/test.png?itok=fsdkjfksdj

## 🐣 Getting started.

Your site will need at least one Drupal image style which includes the responsive image effect.

## 🤖 Generating image URLs.

To generate the URLs, a Utility service is available:

```php
    $responsiveImageEffectService = \Drupal::getContainer()->get('responsive_image_effect.responsive_image_service');
    $fileUri = 'public://test.png';
    
    // Create a resized image preserving original aspect ratio with a width of 100px.
    // "https://site.localhost/sites/default/files/styles/responsive/public/100/0/0/test.png?itok=fsdkjfksdj"
    $src = $responsiveImageEffectService->responsiveImageUrl($fileUri, ['w' => 100]);
    
    // Create an image 400px wide with the height a maximum ratio of 0.75 (3/4 of width).
    // "https://site.localhost/sites/default/files/styles/responsive/public/400/300/1/test.png?itok=fsdkjfksdj"
    $src = $responsiveImageEffectService->responsiveImageUrl($fileUri, $responsiveImageEffectService->crop(400, 0.75));
    
    // Create a srcset set of images.
    $srcset = $responsiveImageEffectService->makeSrcset($fileUri, [ ['w' => 100, 'h' => 100, 'c' => FALSE], ['w' => 200, 'h' => 200, 'c' => FALSE], ['w' => 300, 'h' => 300, 'c' => FALSE] ]);
```

## ✂ Cropping.

The module makes use of the [focal point](https://drupal.org/project/focal_point) module for cropping to centre any crop dimensions around a given point.

## 🖇️ Drush command utility.

There is a drush command file which allows you to generate URLs.

The general form is:

`drush @docker rig [media_entity_id] [width] [height (optional)] [--crop|--crop=ratio (optional)]`

Where:
 
 * media_entity_id is the entity id of a media entity in Drupal with an image file attached.
 * width is a width in pixels
 * height is a height in pixels (optional)
 * --crop can be used on its own to force the specified width and height
 * --crop=ratio can be used instead of specifying the height which will be calculated from the given ration (e.g. 0.5)

e.g. the following drush command generates a URL for an image with entity id 5, width 300 and height of 210 (0.7 x 300)

`drush @docker rig 5 300 --crop=0.7`

e.g. the following drush command generates a URL for an image with entity id 5, width 300 and height of 500 with a crop

`drush @docker rig 5 500 500 --crop`
