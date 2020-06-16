<?php

/**
 * @file
 * An image effect that uses the URL to decide how to shape an image style.
 */

namespace Drupal\responsive_image_effect\Plugin\ImageEffect;

use Drupal\Component\Utility\Image;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Image\ImageInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\crop\CropStorageInterface;
use Drupal\focal_point\FocalPointManager;
use Drupal\focal_point\Plugin\ImageEffect\FocalPointScaleAndCropImageEffect;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Scales and crops an image resource.
 *
 * @ImageEffect(
 *   id = "image_responsive",
 *   label = @Translation("Responsive"),
 *   description = @Translation("Apply image effects via url query string parameters.")
 * )
 */
class ResponsiveImageEffect extends FocalPointScaleAndCropImageEffect {

  /**
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  public $routeMatch;

  /**
   * Constructs a \Drupal\focal_point\FocalPointEffectBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Psr\Log\LoggerInterface $logger
   *   Image logger.
   * @param \Drupal\focal_point\FocalPointManager $focal_point_manager
   *   Focal point manager.
   * @param \Drupal\crop\CropStorageInterface $crop_storage
   *   Crop storage.
   * @param \Drupal\Core\Config\ImmutableConfig $focal_point_config
   *   Focal point configuration object.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Current request object.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The Drupal route matacher.
   *
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LoggerInterface $logger, FocalPointManager $focal_point_manager, CropStorageInterface $crop_storage, ImmutableConfig $focal_point_config, Request $request, RouteMatchInterface $route_match) {
    $this->routeMatch = $route_match;
    parent::__construct($configuration, $plugin_id, $plugin_definition, $logger, $focal_point_manager, $crop_storage, $focal_point_config, $request);
  }

  /**
   * {@inheritdoc}
   */
  public function transformDimensions(array &$dimensions, $uri) {
    if ($dimensions['width'] && $dimensions['height']) {
      Image::scaleDimensions($dimensions, $this->configuration['w'], $this->configuration['h']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function applyEffect(ImageInterface $image) {
    $this->configuration['width'] = $this->routeMatch->getParameter('width');
    $this->configuration['height'] = $this->routeMatch->getParameter('height');
    $this->configuration['crop'] = $this->routeMatch->getParameter('crop');

    if ($this->configuration['crop']) {
      // Use Focal Point to do the scale and crop!
      $r = parent::applyEffect($image);
    }
    else {
      // Just scale, but only if the width requested is smaller.
      $originalDimensions = $this->getOriginalImageSize();

      if ($originalDimensions && $originalDimensions['width'] && $originalDimensions['width'] < $this->configuration['width']) {
        // Basically do not do anything.
        $this->configuration['width'] = $originalDimensions['width'];
        $this->configuration['height'] = $originalDimensions['height'];
      }
      $r = $image->scale($this->configuration['width'], $this->configuration['height']);
    }

    if (!$r) {
      $this->logger->error('Responsive image generation failed using the %toolkit toolkit on %path (%mimetype, %dimensions)', [
        '%toolkit' => $image->getToolkitId(),
        '%path' => $image->getSource(),
        '%mimetype' => $image->getMimeType(),
        '%dimensions' => $image->getWidth() . 'x' . $image->getHeight(),
      ]);
    }

    return $r;
  }

  /**
   * {@inheritdoc}
   *
   * @codeCoverageIgnore
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.factory')->get('image'),
      $container->get('focal_point.manager'),
      $container->get('entity_type.manager')->getStorage('crop'),
      $container->get('config.factory')->get('focal_point.settings'),
      \Drupal::request(),
      \Drupal::routeMatch()
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return parent::defaultConfiguration() + [
        'crop' => NULL,
      ];
  }

}
