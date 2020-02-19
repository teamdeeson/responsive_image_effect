<?php

namespace Drupal\responsive_image_effect\Service;

use Drupal\Core\StreamWrapper\StreamWrapperManager;
use Drupal\image\Entity\ImageStyle;

class ResponsiveImageEffectService {

  public function makeSrcset($uri, $sizes) {
    $r = [];
    foreach ($sizes as $s) {
      if (!is_array($s)) {
        $s = ['w' => $s];
      }
      $r[] = $this->responsiveImageUrl($uri, $s) . " {$s['w']}w";
    }
    return implode(', ', $r);
  }

  /**
   * Build a URL to a responsive image style.
   *
   * @param $source_file_uri
   * @param array $p
   * @param string $image_style_name
   *
   * @return string
   */
  public function responsiveImageUrl($source_file_uri, array $p, $image_style_name = 'responsive') {
    $image_style = ImageStyle::load($image_style_name);

    $width = $p['w'];
    $height = !empty($p['h']) ? $p['h'] : $p['w'];
    $crop = !empty($p['c']) ? 1 : 0;

    $derivative_uri = $this->buildUri($source_file_uri, $image_style->id(), $width, $height, $crop);

    $derivative_url = file_create_url($derivative_uri);

    // @todo security goes here.
    // Append the query string with the token, if necessary.
    //if ($token_query) {
    //  $derivative_url .= (strpos($derivative_url, '?') !== FALSE ? '&' : '?') . UrlHelper::buildQuery($token_query);
    //}

    return $derivative_url;
  }

  public function crop($width, $ratio = 9 / 16) {
    return ['w' => $width, 'h' => (int) ($width * $ratio), 'c' => TRUE];
  }

  public function cropAll(array $widths, $ratio = 9 / 16) {
    return array_map(function ($w) use ($ratio) {
      return $this->crop($w, $ratio);
    }, $widths);
  }

  /**
   * Build a uri to a responsive image file.
   *
   * @param string $file_uri
   *   The source file uri in the form scheme://path/file.name
   * @param string $image_style_id
   *   The name of the image style, e.g. 'responsive'
   * @param int $width
   * @param int $height
   * @param int $crop
   *
   * @return string
   */
  public function buildUri($file_uri, $image_style_id, $width, $height, $crop) {
    $source_scheme = $scheme = StreamWrapperManager::getScheme($file_uri);
    $default_scheme = \Drupal::config('system.file')->get('default_scheme');

    if ($source_scheme) {
      $path = StreamWrapperManager::getTarget($file_uri);
      // @todo might need something in here if the source and default schemes differ.
    }
    else {
      $path = $file_uri;
      $source_scheme = $scheme = $default_scheme;
    }

    return "$scheme://styles/{$image_style_id}/{$source_scheme}/{$width}/{$height}/{$crop}/{$path}";
  }

}
