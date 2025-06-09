<?php

namespace Drupal\tfa_sms\EventSubscriber;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\user\UserDataInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Event subscriber for TFA SMS events.
 */
class TfaSmsEventSubscriber implements EventSubscriberInterface {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The user data service.
   *
   * @var \Drupal\user\UserDataInterface
   */
  protected $userData;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Constructs a new TfaSmsEventSubscriber object.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Drupal\user\UserDataInterface $user_data
   *   The user data service.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   */
  public function __construct(AccountProxyInterface $current_user, UserDataInterface $user_data, RouteMatchInterface $route_match) {
    $this->currentUser = $current_user;
    $this->userData = $user_data;
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      KernelEvents::REQUEST => ['onRequest', 0],
    ];
  }

  /**
   * React to the request event.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The request event.
   */
  public function onRequest(RequestEvent $event) {
    if (!$event->isMainRequest()) {
      return;
    }

    $route_name = $this->routeMatch->getRouteName();
    $request = $event->getRequest();

    // Clear code on logout
    if ($route_name === 'user.logout') {
      $this->clearCode();
    }

    // Clear code on login form and login attempt
    if ($route_name === 'user.login' || $route_name === 'user.login.http') {
      // Получаем имя пользователя из запроса
      $username = $request->request->get('name');
      if ($username) {
        // Находим пользователя по имени
        $user = \Drupal::entityTypeManager()
          ->getStorage('user')
          ->loadByProperties(['name' => $username]);
        if ($user) {
          $user = reset($user);
          $this->clearCode($user->id());
        }
      }
    }
  }

  /**
   * Clear the verification code and related data.
   *
   * @param int|null $uid
   *   The user ID. If NULL, uses the current user.
   */
  protected function clearCode($uid = NULL) {
    if ($uid === NULL && $this->currentUser->isAuthenticated()) {
      $uid = $this->currentUser->id();
    }

    if ($uid) {
      $this->userData->delete('tfa_sms', $uid, 'code');
      $this->userData->delete('tfa_sms', $uid, 'last_send');
      $this->userData->delete('tfa_sms', $uid, 'attempts');
    }
  }

} 