<?php

namespace Drupal\responsive_image_effect\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

class RouteSubscriber extends RouteSubscriberBase {
  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    // We are removing the Drupal standard way of managing image styles in favour
    // of our far more better one.
    $collection->remove('image.style_public');
  }
}
