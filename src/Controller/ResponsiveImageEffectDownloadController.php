<?php

namespace Drupal\responsive_image_effect\Controller;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\responsive_image_effect\Plugin\ImageEffect\ResponsiveImageEffect;
use Drupal\responsive_image_effect\Service\ResponsiveImageEffectService;
use Drupal\system\FileDownloadController;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/**
 * Defines a controller to serve image styles.
 */
class ResponsiveImageEffectDownloadController extends FileDownloadController {

  /**
   * The lock backend.
   *
   * @var \Drupal\Core\Lock\LockBackendInterface
   */
  protected $lock;

  /**
   * The image factory.
   *
   * @var \Drupal\Core\Image\ImageFactory
   */
  protected $imageFactory;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * @var \Drupal\responsive_image_effect\Service\ResponsiveImageEffectService
   */
  protected $responsiveImageEffectService;

  /**
   * Constructs a ImageStyleDownloadController object.
   *
   * @param \Drupal\Core\Lock\LockBackendInterface $lock
   *   The lock backend.
   * @param \Drupal\Core\Image\ImageFactory $image_factory
   *   The image factory.
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $stream_wrapper_manager
   *   The stream wrapper manager.
   * @param \Drupal\responsive_image_effect\Service\ResponsiveImageEffectService $responsiveImageEffectService
   *   A service with some useful functions on it.
   */
  public function __construct(LockBackendInterface $lock, ImageFactory $image_factory, StreamWrapperManagerInterface $stream_wrapper_manager = NULL, ResponsiveImageEffectService $responsiveImageEffectService) {
    parent::__construct($stream_wrapper_manager);
    $this->lock = $lock;
    $this->imageFactory = $image_factory;
    $this->logger = $this->getLogger('image');
    $this->responsiveImageEffectService = $responsiveImageEffectService;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('lock'),
      $container->get('image.factory'),
      $container->get('stream_wrapper_manager'),
      $container->get('responsive_image_effect.responsive_image_service')
    );
  }

  /**
   * Generates a derivative, given a style and image path.
   *
   * After generating an image, transfer it to the requesting agent.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param \Drupal\image\ImageStyleInterface $image_style
   *   The image style to deliver.
   * @param string $scheme
   *   The file scheme, defaults to 'private'.
   * @param string $width
   *   The width to resize the image to.
   * @param string $height
   *   The height to resize the image to.
   * @param string $crop
   *   Whether to crop at the given dimensions.
   *
   *
   * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Symfony\Component\HttpFoundation\Response
   *   The transferred file as response or some error response.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   Thrown when the file request is invalid.
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   Thrown when the user does not have access to the file.
   * @throws \Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException
   *   Thrown when the file is still being generated.
   */
  public function deliver(Request $request, $scheme, $image_style, $width, $height, $crop) {
    $target = $request->query->get('file');
    $image_uri = $scheme . '://' . $target;
    $image_style = ImageStyle::load($image_style);

    $width = (int) $width;
    $height = (int) $height;
    $crop = (int) $crop;

    // Make sure this is a valid image style and file scheme.
    $valid = !empty($image_style) && $this->streamWrapperManager->isValidScheme($scheme);

    // Make sure the width and height make sense.
    $valid &= $width > 0 && $height >= 0;

    // Make sure the crop makes sense.
    $valid &= $crop === 0 || $crop === 1;

    // Make sure one of the image styles effects is a responsive image effect.
    $valid &= $this->_imageStyleHasResponsiveEffect($image_style);

    // @todo - security goes here.
    /*
    if (!$this->config('image.settings')->get('allow_insecure_derivatives') || strpos(ltrim($target, '\/'), 'styles/') === 0) {
      $valid &= hash_equals($image_style->getPathToken($image_uri), $request->query->get(IMAGE_DERIVATIVE_TOKEN, ''));
    }
    */

    if (!$valid) {
      // Return a 404 (Page Not Found) rather than a 403 (Access Denied) as the
      // image token is for DDoS protection rather than access checking. 404s
      // are more likely to be cached (e.g. at a proxy) which enhances
      // protection from DDoS.
      throw new NotFoundHttpException();
    }

    $derivative_uri = $this->responsiveImageEffectService->buildUri($image_uri, $image_style->id(), $width, $height, $crop);
    $headers = [];

    // If using the private scheme, let other modules provide headers and
    // control access to the file.
    if ($scheme == 'private') {
      $headers = $this->moduleHandler()->invokeAll('file_download', [$image_uri]);
      if (in_array(-1, $headers) || empty($headers)) {
        throw new AccessDeniedHttpException();
      }
    }

    // Don't try to generate file if source is missing.
    if (!file_exists($image_uri)) {
      // If the image style converted the extension, it has been added to the
      // original file, resulting in filenames like image.png.jpeg. So to find
      // the actual source image, we remove the extension and check if that
      // image exists.
      $path_info = pathinfo($image_uri);
      $converted_image_uri = $path_info['dirname'] . DIRECTORY_SEPARATOR . $path_info['filename'];
      if (!file_exists($converted_image_uri)) {
        $this->logger->notice('Source image at %source_image_path not found while trying to generate derivative image at %derivative_path.', ['%source_image_path' => $image_uri, '%derivative_path' => $derivative_uri]);
        return new Response($this->t('Error generating image, missing source file.'), 404);
      }
      else {
        // The converted file does exist, use it as the source.
        $image_uri = $converted_image_uri;
      }
    }

    // Don't start generating the image if the derivative already exists or if
    // generation is in progress in another thread.
    if (!file_exists($derivative_uri)) {
      $lock_name = 'image_style_deliver:' . $image_style->id() . ':' . Crypt::hashBase64($image_uri);
      $lock_acquired = $this->lock->acquire($lock_name);
      if (!$lock_acquired) {
        // Tell client to retry again in 3 seconds. Currently no browsers are
        // known to support Retry-After.
        throw new ServiceUnavailableHttpException(3, $this->t('Image generation in progress. Try again shortly.'));
      }
    }

    // Try to generate the image, unless another thread just did it while we
    // were acquiring the lock.
    $success = file_exists($derivative_uri) || $image_style->createDerivative($image_uri, $derivative_uri);

    if (!empty($lock_acquired)) {
      $this->lock->release($lock_name);
    }

    if ($success) {
      $image = $this->imageFactory->get($derivative_uri);
      $uri = $image->getSource();
      $headers += [
        'Content-Type' => $image->getMimeType(),
        'Content-Length' => $image->getFileSize(),
      ];
      // \Drupal\Core\EventSubscriber\FinishResponseSubscriber::onRespond()
      // sets response as not cacheable if the Cache-Control header is not
      // already modified. We pass in FALSE for non-private schemes for the
      // $public parameter to make sure we don't change the headers.
      return new BinaryFileResponse($uri, 200, $headers, $scheme !== 'private');
    }
    else {
      $this->logger->notice('Unable to generate the derived image located at %path.', ['%path' => $derivative_uri]);
      return new Response($this->t('Error generating image.'), 500);
    }
  }

  /**
   * Check if an image style includes a responsive image effect.
   *
   * @param $image_style
   *
   * @return bool
   */
  protected function _imageStyleHasResponsiveEffect($image_style) {
    foreach ($image_style->getEffects() as $effect) {
      if ($effect instanceof ResponsiveImageEffect) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
