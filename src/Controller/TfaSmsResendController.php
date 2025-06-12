<?php

namespace Drupal\tfa_sms\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Url;

/**
 * Controller for TFA SMS resend functionality.
 */
class TfaSmsResendController extends ControllerBase {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Constructs a new TfaSmsResendController object.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   */
  public function __construct(AccountInterface $current_user) {
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user')
    );
  }

  /**
   * Resend the TFA code.
   *
   * @param int $uid
   *   The user ID.
   * @param string $method
   *   The TFA method.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response.
   */
  public function resendCode($uid, $method) {
    // Check if the current user is the same as the requested user
    if ($this->currentUser->id() != $uid) {
      $this->messenger()->addError($this->t('You are not authorized to perform this action.'));
      return new RedirectResponse(Url::fromRoute('<front>')->toString());
    }

    // Get the TFA plugin
    $tfa = \Drupal::service('tfa.manager')->getPlugin($uid, $method);
    if (!$tfa) {
      $this->messenger()->addError($this->t('Invalid TFA method.'));
      return new RedirectResponse(Url::fromRoute('<front>')->toString());
    }

    // Send the code
    if ($tfa->sendCode()) {
      $this->messenger()->addStatus($this->t('A new verification code has been sent to your phone.'));
    }
    else {
      $this->messenger()->addError($this->t('Failed to send verification code. Please try again later.'));
    }

    // Redirect back to the TFA form
    return new RedirectResponse(Url::fromRoute('tfa.validation.form', [
      'uid' => $uid,
      'method' => $method,
    ])->toString());
  }

  /**
   * {@inheritdoc}
   */
  public function getContext() {
    return is_array($this->context) ? $this->context : [];
  }

} 