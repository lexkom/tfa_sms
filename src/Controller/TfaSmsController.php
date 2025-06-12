<?php

namespace Drupal\tfa_sms\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\user\UserDataInterface;
use Drupal\tfa_sms\Service\SmsSender;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Controller for TFA SMS functionality.
 */
class TfaSmsController extends ControllerBase {

  /**
   * The user data service.
   *
   * @var \Drupal\user\UserDataInterface
   */
  protected $userData;

  /**
   * The SMS sender service.
   *
   * @var \Drupal\tfa_sms\Service\SmsSender
   */
  protected $smsSender;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Constructs a new TfaSmsController object.
   *
   * @param \Drupal\user\UserDataInterface $user_data
   *   The user data service.
   * @param \Drupal\tfa_sms\Service\SmsSender $sms_sender
   *   The SMS sender service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(UserDataInterface $user_data, SmsSender $sms_sender, LoggerChannelFactoryInterface $logger_factory) {
    $this->userData = $user_data;
    $this->smsSender = $sms_sender;
    $this->loggerFactory = $logger_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('user.data'),
      $container->get('tfa_sms.sender'),
      $container->get('logger.factory')
    );
  }

  /**
   * Resend verification code.
   *
   * @param int $uid
   *   The user ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response.
   */
  public function resendCode($uid, Request $request) {
    $response = new AjaxResponse();
    $logger = $this->loggerFactory->get('tfa_sms');

    // Очищаем старый код и счетчик попыток
    $this->userData->delete('tfa_sms', $uid, 'code');
    $this->userData->delete('tfa_sms', $uid, 'last_send');
    $this->userData->delete('tfa_sms', $uid, 'attempts');

    // Генерируем новый код
    $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $this->userData->set('tfa_sms', $uid, 'code', $code);
    $this->userData->set('tfa_sms', $uid, 'last_send', time());

    // Получаем номер телефона пользователя
    $user = \Drupal\user\Entity\User::load($uid);
    if ($user && $user->hasField('field_phone_number') && !$user->get('field_phone_number')->isEmpty()) {
      $phone_number = $user->get('field_phone_number')->value;
      $message = $this->t('Your verification code is: @code', ['@code' => $code]);

      if ($this->smsSender->send($phone_number, $message)) {
        $this->messenger()->addStatus($this->t('A new verification code has been sent to your phone.'));
        $logger->info('Verification code sent to @phone for user @uid', [
          '@phone' => $phone_number,
          '@uid' => $uid,
        ]);
      }
      else {
        $this->messenger()->addError($this->t('Failed to send verification code. Please try again later.'));
        $logger->error('Failed to send verification code to @phone for user @uid', [
          '@phone' => $phone_number,
          '@uid' => $uid,
        ]);
      }
    }
    else {
      $this->messenger()->addError($this->t('Failed to send verification code: phone number not found.'));
      $logger->error('Phone number not found for user @uid', ['@uid' => $uid]);
    }

    // Получаем сообщения и добавляем их в ответ
    $messages = $this->messenger()->all();
    foreach ($messages as $type => $type_messages) {
      foreach ($type_messages as $message) {
        $response->addCommand(new MessageCommand($message, NULL, ['type' => $type]));
      }
    }

    return $response;
  }

} 