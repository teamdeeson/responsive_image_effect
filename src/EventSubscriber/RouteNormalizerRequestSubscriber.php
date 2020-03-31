<?php

namespace Drupal\responsive_image_effect\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Cmf\Component\Routing\RouteObjectInterface;

class RouteNormalizerRequestSubscriber implements EventSubscriberInterface {

  public static function getSubscribedEvents() {
    return [
      KernelEvents::REQUEST => ['onKernelRequestRedirect'],
    ];
  }

  /**
   * Prevent the Redirect module from redirecting image style URLs to their normalised path.
   *
   * @param \Symfony\Component\HttpKernel\Event\GetResponseEvent $event
   */
  public function onKernelRequestRedirect(GetResponseEvent $event) {
    $request = $event->getRequest();
    $route_name = $request->get(RouteObjectInterface::ROUTE_NAME);

    if ($route_name === 'responsive_image_effect.style_public') {
      $request->attributes->set('_disable_route_normalizer', TRUE);
    }
  }

}
