<?php

namespace Drupal\responsive_image_effect\Service;

use Drupal\image\Entity\ImageStyle;
use Drupal\stage_file_proxy\FetchManager;
use Drupal\Core\StreamWrapper\StreamWrapperManager;

/**
 * Fetch manager.
 */
class ResponsiveImageEffectFetchManager extends FetchManager {

  /**
   * {@inheritdoc}
   */
  public function styleOriginalPath($uri, $style_only = TRUE) {
    $scheme = StreamWrapperManager::getScheme($uri);

    if ($scheme) {
      $path = StreamWrapperManager::getTarget($uri);
    }
    else {
      $path = $uri;
    }

    // It is a styles path, so we extract the different parts.
    if (strpos($path, 'styles') === 0) {
      // Then the path is like styles/[style_name]/[schema]/[original_path].
      $style_name = preg_replace('/styles\/([^\/]*)\/.*/', '$1', $path);
      $image_style = ImageStyle::load($style_name);

      /** @var \Drupal\responsive_image_effect\Service\ResponsiveImageEffectService $responsive_image_effect_service */
      $responsive_image_effect_service = \Drupal::service('responsive_image_effect.responsive_image_service');
 
      if ($responsive_image_effect_service->imageStyleHasResponsiveEffect($image_style)) {
        // Then the path is like styles/[style_name]/[schema]/[w]/[h]/[crop]/[original_path].
        return preg_replace('/styles\/.*\/(.*)\/.*\/.*\/.*\/(.*)/U', '$1://$2', $path);
      }
    }

    return parent::styleOriginalPath($uri, $style_only);
  }

}
