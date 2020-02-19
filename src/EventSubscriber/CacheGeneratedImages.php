<?php

namespace Drupal\responsive_image_effect\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class CacheGeneratedImages implements EventSubscriberInterface {

  /**
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $currentRoute;

  /**
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  public function __construct(RouteMatchInterface $currentRoute, ConfigFactoryInterface $config_factory) {
    $this->currentRoute = $currentRoute;
    $this->config = $config_factory->get('system.performance');
  }

  public static function getSubscribedEvents() {
    return [
      KernelEvents::RESPONSE => ['onRespond'],
    ];
  }

  public function onRespond(FilterResponseEvent $event) {
    $r = $event->getResponse();
    if ($r instanceof BinaryFileResponse && $this->currentRoute->getRouteName() == 'image.style_public') {
      $max_age = $this->config->get('cache.page.max_age');
      $r->headers->set('Cache-Control', 'public, max-age=' . $max_age);
      $r->setAutoEtag();
    }
  }

}
