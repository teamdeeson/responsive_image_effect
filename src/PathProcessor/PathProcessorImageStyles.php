<?php

namespace Drupal\responsive_image_effect\PathProcessor;

use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\responsive_image_effect\Plugin\ImageEffect\ResponsiveImageEffect;
use Symfony\Component\HttpFoundation\Request;

/**
 * Defines a path processor to rewrite image styles URLs.
 *
 * As the route system does not allow arbitrary amount of parameters convert
 * the file path to a query parameter on the request.
 *
 * This processor handles two different cases:
 * - public image styles: In order to allow the webserver to serve these files
 *   directly, the route is registered under the same path as the image style so
 *   it took over the first generation. Therefore the path processor converts
 *   the file path to a query parameter.
 * - private image styles: In contrast to public image styles, private
 *   derivatives are already using system/files/styles. Similar to public image
 *   styles, it also converts the file path to a query parameter.
 */
class PathProcessorImageStyles implements InboundPathProcessorInterface {

  /**
   * The stream wrapper manager service.
   *
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface
   */
  protected $streamWrapperManager;

  /**
   * Constructs a new PathProcessorImageStyles object.
   *
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $stream_wrapper_manager
   *   The stream wrapper manager service.
   */
  public function __construct(StreamWrapperManagerInterface $stream_wrapper_manager) {
    $this->streamWrapperManager = $stream_wrapper_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function processInbound($path, Request $request) {
    $directory_path = $this->streamWrapperManager->getViaScheme('public')->getDirectoryPath();
    if (strpos($path, '/' . $directory_path . '/styles/') === 0) {
      $path_prefix = '/' . $directory_path . '/styles/';
    }
    // Check if the string '/system/files/styles/' exists inside the path,
    // that means we have a case of private file's image style.
    elseif (strpos($path, '/system/files/styles/') !== FALSE) {
      $path_prefix = '/system/files/styles/';
      $path = substr($path, strpos($path, $path_prefix), strlen($path));
    }
    else {
      return $path;
    }

    // Strip out path prefix.
    $rest = preg_replace('|^' . preg_quote($path_prefix, '|') . '|', '', $path);

    // Give up if too few args.
    if (substr_count($rest, '/') < 3) {
      return $path;
    }

    // First decide if we are dealing with a classic image style or one of our
    // responsive image styles.
    list($image_style, $scheme, $filepath) = explode('/', $rest, 3);

    if (!$this->_imageStyleHasResponsiveEffect($image_style)) {
      // This is a classic image style.
      $request->query->set('file', $filepath);
      return $path_prefix . $image_style . '/' . $scheme;
    }

    list($width, $height, $crop, $file) = explode('/', $filepath, 4);

    // Set the file as query parameter.
    $request->query->set('file', $file);

    return $path_prefix . $image_style . '/' . $scheme . '/' . $width . '/' . $height . '/' . $crop;
  }


  /**
   * Check if an image style includes a responsive image effect.
   *
   * @param $image_style
   *
   * @return bool
   */
  protected function _imageStyleHasResponsiveEffect($image_style) {
    $image_style = ImageStyle::load($image_style);
    foreach ($image_style->getEffects() as $effect) {
      if ($effect instanceof ResponsiveImageEffect) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
