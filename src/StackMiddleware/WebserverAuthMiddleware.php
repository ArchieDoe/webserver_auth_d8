<?php

namespace Drupal\webserver_auth\StackMiddleware;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Drupal\Core\Session\SessionConfigurationInterface;
use Drupal\webserver_auth\WebserverAuthHelper;
use Drupal\Core\Extension\ModuleHandlerInterface;


/**
 * Maintenance mode subscriber to log out users.
 */
class WebserverAuthMiddleware implements HttpKernelInterface {

  /**
   * The decorated kernel.
   *
   * @var \Symfony\Component\HttpKernel\HttpKernelInterface
   */
  protected $httpKernel;

  /**
   * The session configuration.
   *
   * @var \Drupal\Core\Session\SessionConfigurationInterface
   */
  protected $sessionConfiguration;

  /**
   * Helper class that brings some helper functionality related to webserver authentication.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $helper;

  /**
   * Module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $module_handler;

  /**
   * Constructs a WebserverAuthMiddleware object.
   *
   * @param \Symfony\Component\HttpKernel\HttpKernelInterface $http_kernel
   *   The decorated kernel.
   *
   * @param \Drupal\Core\Session\SessionConfigurationInterface $session_configuration
   *   The session configuration.
   *
   * @param \Drupal\webserver_auth\WebserverAuthHelper $helper
   *   Helper class that brings some helper functionality related to webserver authentication.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *  Module handler service.
   */
  public function __construct(HttpKernelInterface $http_kernel, SessionConfigurationInterface $session_configuration, WebserverAuthHelper $helper, ModuleHandlerInterface $module_handler) {
    $this->httpKernel = $http_kernel;
    $this->sessionConfiguration = $session_configuration;
    $this->helper = $helper;
    $this->module_handler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public function handle(Request $request, $type = self::MASTER_REQUEST, $catch = TRUE) {
    /**
     * We assuming that user should be logged in if remote user exists and valid.
     * In this case we need to prevent any page to be loaded from cache (to be able to
     * affect on Authentication and log in remote user).
     *
     * We only need this code if Drupal page_cache module is enabled.
     */

    // Checking if page_cache module installed.
    if ($this->module_handler->moduleExists('page_cache')) {

      // Doing this for MASTER_REQUEST only.
      if ($type === self::MASTER_REQUEST && PHP_SAPI !== 'cli') {

        // Getting authname. We don't need to validate it here, that will be done later
        // by authentication function.
        if ($this->helper->getRemoteUser($request)) {

          // Checking that current session is not stored in cookies yet.
          $request_options = $this->sessionConfiguration->getOptions($request);

          // If cookies aren't set already for current session - adding placeholder which will
          // block page cache from being loaded.
          if (!$request->cookies->has($request_options['name'])) {
            $request->cookies->set($request_options['name'], 'cache_blocked');
          }
        }
      }
    }

    return $this->httpKernel->handle($request, $type, $catch);
  }
}
