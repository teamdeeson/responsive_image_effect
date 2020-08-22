<?php

namespace Drupal\responsive_image_effect\Entity;

use Drupal\Core\File\Exception\FileException;
use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManager;
use Drupal\image\Entity\ImageStyle;

class ResponsiveImageStyle extends ImageStyle {

  /**
   * {@inheritdoc}
   */
  public function flush($path = NULL) {
    /** @var \Drupal\responsive_image_effect\Service\ResponsiveImageEffectService $responsive_image_effect_service */
    $responsive_image_effect_service = \Drupal::service('responsive_image_effect.responsive_image_service');

    if (empty($path) || !$responsive_image_effect_service->imageStyleHasResponsiveEffect($this)) {
      return parent::flush($path);
    }

    $source_scheme = $scheme = StreamWrapperManager::getScheme($path);
    $default_scheme = $this->fileDefaultScheme();

    if ($source_scheme) {
      $relative_path = StreamWrapperManager::getTarget($path);
      // The scheme of derivative image files only needs to be computed for
      // source files not stored in the default scheme.
      if ($source_scheme != $default_scheme) {
        $class = $this->getStreamWrapperManager()->getClass($source_scheme);
        $is_writable = NULL;
        if ($class) {
          $is_writable = $class::getType() & StreamWrapperInterface::WRITE;
        }

        // Compute the derivative URI scheme. Derivatives created from writable
        // source stream wrappers will inherit the scheme. Derivatives created
        // from read-only stream wrappers will fall-back to the default scheme.
        $scheme = $is_writable ? $source_scheme : $default_scheme;
      }
    }
    else {
      $relative_path = $path;
      $source_scheme = $scheme = $default_scheme;
    }

    // Recurse over width, height and crop directories deleting $relative path if it exists.
    try {
      $base_style_path = "$scheme://styles/{$this->id()}/$source_scheme/";
      $directory = new \RecursiveDirectoryIterator($base_style_path);
      $iterator = new \RecursiveIteratorIterator($directory);
      $relative_path_regex = preg_quote($relative_path, '/');
      $style_files_iterator = new \RegexIterator($iterator, "/^.+{$relative_path_regex}$/i", \RecursiveRegexIterator::GET_MATCH);

      /** @var \Drupal\Core\File\FileSystemInterface $file_system */
      $file_system = \Drupal::service('file_system');

      foreach ($style_files_iterator as $style_files) {
        foreach ($style_files as $derivative_uri) {
          if (file_exists($derivative_uri)) {
            try {
              $file_system->delete($derivative_uri);
            }
            catch (FileException $e) {
              // Ignore failed deletes.
            }
          }
        }
      }
    }
    catch (\Exception $e) {
      watchdog_exception('responsive_image_effect', $e, 'problem finding responsive image derivatives');
    }

    return $this;
  }

}
