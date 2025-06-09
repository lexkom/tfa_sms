<?php

namespace Drupal\tfa_sms\Plugin\TfaValidation;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\tfa\Plugin\TfaBasePlugin;
use Drupal\tfa\Plugin\TfaValidationInterface;
use Drupal\tfa\TfaUserDataTrait;
use Drupal\tfa_sms\Service\SmsSender;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\user\UserDataInterface;
use Drupal\encrypt\EncryptionProfileManagerInterface;
use Drupal\encrypt\EncryptServiceInterface;
use Drupal\user\Entity\User;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @TfaValidation(
 *   id = "tfa_sms",
 *   label = @Translation("SMS TFA"),
 *   description = @Translation("TFA via SMS using SMS Framework"),
 *   setupPluginId = "tfa_sms_setup",
 *   sendPluginId = "tfa_sms_send",
 *   fallbackPluginId = "tfa_sms_fallback",
 *   isFallback = FALSE
 * )
 */
class SmsTfa extends TfaBasePlugin implements TfaValidationInterface, ContainerFactoryPluginInterface {
  use TfaUserDataTrait;

  /**
   * The SMS sender service.
   *
   * @var \Drupal\tfa_sms\Service\SmsSender
   */
  protected $smsSender;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The context data for this validation.
   *
   * @var array
   */
  protected $context = [];

  /**
   * The error messages.
   *
   * @var array
   */
  protected $errorMessages = [];

  /**
   * Maximum number of verification attempts.
   */
  const MAX_ATTEMPTS = 3;

  /**
   * Minimum time between code resends in seconds.
   */
  const RESEND_INTERVAL = 30;

  /**
   * The key used to store the verification code.
   */
  const CODE_KEY = 'code';

  /**
   * The key used to store the last send time.
   */
  const LAST_SEND_KEY = 'last_send';

  /**
   * The key used to store the attempts count.
   */
  const ATTEMPTS_KEY = 'attempts';

  /**
   * Constructs a new SmsTfa object.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, UserDataInterface $user_data, EncryptionProfileManagerInterface $encryption_profile_manager, EncryptServiceInterface $encrypt_service, SmsSender $sms_sender, ConfigFactoryInterface $config_factory, LoggerChannelFactoryInterface $logger_factory, RequestStack $request_stack) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $user_data, $encryption_profile_manager, $encrypt_service);
    $this->smsSender = $sms_sender;
    $this->configFactory = $config_factory;
    $this->loggerFactory = $logger_factory;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('user.data'),
      $container->get('encrypt.encryption_profile.manager'),
      $container->get('encryption'),
      $container->get('tfa_sms.sender'),
      $container->get('config.factory'),
      $container->get('logger.factory'),
      $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function begin() {
    $this->logDebug('Starting SMS TFA process');
    
    // Reset attempts counter
    $this->userData->set('tfa_sms', $this->uid, self::ATTEMPTS_KEY, 0);
    
    // Check if we already have a valid code
    $code = $this->getCode();
    $last_send = (int) $this->userData->get('tfa_sms', $this->uid, self::LAST_SEND_KEY) ?: 0;
    
    // If we don't have a code or it's expired (more than 5 minutes old), generate a new one
    if (!$code || (time() - $last_send) > 300) {
      $this->logDebug('No valid code found or code expired, generating new one');
      if (!$this->sendCode()) {
        $this->logDebug('Failed to send verification code');
        return FALSE;
      }
      $this->logDebug('New code generated and sent');
    }
    else {
      $this->logDebug('Using existing code: @code', ['@code' => $code]);
    }
    
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function ready() {
    $phone = $this->getUserPhoneNumber();
    $this->logDebug('Checking if user is ready for SMS TFA. Phone number: @phone', ['@phone' => $phone ?? 'not set']);
    return $phone !== NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getForm(array $form, FormStateInterface $form_state) {
    $this->logDebug('Building verification form');
    
    // Add our library
    $form['#attached']['library'][] = 'tfa_sms/tfa_sms';
    
    // Add resend URL to JavaScript settings
    $resend_url = Url::fromRoute('tfa_sms.resend_code', [
      'uid' => $this->uid,
    ])->toString();
    $form['#attached']['drupalSettings']['tfaSms']['resendUrl'] = $resend_url;
    
    $form['sms_code'] = [
      '#type' => 'textfield',
      '#title' => t('Verification Code'),
      '#required' => TRUE,
      '#attributes' => ['autocomplete' => 'off'],
      '#description' => t('Enter the verification code sent to your phone.'),
    ];

    // Show remaining attempts if any.
    $attempts = (int) $this->userData->get('tfa_sms', $this->uid, self::ATTEMPTS_KEY) ?: 0;
    if ($attempts > 0) {
      $remaining = self::MAX_ATTEMPTS - $attempts;
      $form['sms_code']['#description'] .= ' ' . t('You have @remaining attempts remaining.', ['@remaining' => $remaining]);
      $this->logDebug('User has @remaining attempts remaining', ['@remaining' => $remaining]);
    }

    // Add submit button
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => t('Подтвердить'),
    ];

    // Add resend code link as markup
    $last_send = (int) $this->userData->get('tfa_sms', $this->uid, self::LAST_SEND_KEY) ?: 0;
    if ($last_send > 0) {
      $time_passed = time() - $last_send;
      if ($time_passed < self::RESEND_INTERVAL) {
        $time_left = self::RESEND_INTERVAL - $time_passed;
        $form['resend_code'] = [
          '#markup' => '<div class="resend-code disabled"><span class="resend-text">' . t('Resend code (@time_left s)', ['@time_left' => $time_left]) . '</span></div>',
        ];
      }
      else {
        $form['resend_code'] = [
          '#markup' => '<div class="resend-code"><a href="#" class="resend-link">' . t('Resend code') . '</a></div>',
        ];
      }
    }
    else {
      $form['resend_code'] = [
        '#markup' => '<div class="resend-code"><a href="#" class="resend-link">' . t('Resend code') . '</a></div>',
      ];
    }

    // Add hidden field for user ID
    $form['uid'] = [
      '#type' => 'hidden',
      '#value' => $this->uid,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array $form, FormStateInterface $form_state) {
    // Если это запрос на повторную отправку кода, пропускаем валидацию
    if ($form_state->getValue('resend_code')) {
      $this->logDebug('Skipping validation for resend code request');
      return TRUE;
    }

    $code = $form_state->getValue('sms_code');
    $this->logDebug('Validating code: @code', ['@code' => $code]);
    
    // Get attempts count
    $attempts = (int) $this->userData->get('tfa_sms', $this->uid, self::ATTEMPTS_KEY) ?: 0;

    // Check if maximum attempts reached
    if ($attempts >= self::MAX_ATTEMPTS) {
      $this->logDebug('Maximum attempts reached');
      $this->errorMessages['sms_code'] = t('Maximum number of attempts reached. Please request a new code.');
      return FALSE;
    }

    // Increment attempts counter
    $attempts++;
    $this->userData->set('tfa_sms', $this->uid, self::ATTEMPTS_KEY, $attempts);
    $this->logDebug('Incrementing attempts counter to @attempts', ['@attempts' => $attempts]);

    if (!$this->validateCode($code)) {
      $this->logDebug('Invalid code entered');
      $this->errorMessages['sms_code'] = t('Invalid verification code.');
      return FALSE;
    }

    $this->logDebug('Code validated successfully');
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array $form, FormStateInterface &$form_state) {
    // Check if this is a resend code request
    if ($form_state->getValue('resend_code')) {
      $this->logDebug('Resending verification code');
      
      // Очищаем старый код и счетчик попыток
      $this->userData->delete('tfa_sms', $this->uid, self::CODE_KEY);
      $this->userData->delete('tfa_sms', $this->uid, self::ATTEMPTS_KEY);
      $this->userData->delete('tfa_sms', $this->uid, self::LAST_SEND_KEY);
      
      // Генерируем и отправляем новый код
      $code = $this->generateCode();
      $phone_number = $this->getUserPhoneNumber();
      if ($phone_number) {
        $message = t('Your verification code is: @code', ['@code' => $code]);
        $this->userData->set('tfa_sms', $this->uid, self::LAST_SEND_KEY, time());
        
        if ($this->smsSender->send($phone_number, $message)) {
          $this->messenger()->addStatus(t('A new verification code has been sent to your phone.'));
          $this->logDebug('New code sent: @code', ['@code' => $code]);
        }
        else {
          $this->messenger()->addError(t('Failed to send verification code. Please try again later.'));
          $this->logDebug('Failed to send new code');
        }
      }
      else {
        $this->messenger()->addError(t('Failed to send verification code: phone number not found.'));
        $this->logDebug('Phone number not found for user');
      }

      // If this is an AJAX request, return JSON response
      if ($form_state->getRequest()->isXmlHttpRequest()) {
        $response = new \Drupal\Core\Ajax\AjaxResponse();
        $response->addCommand(new \Drupal\Core\Ajax\MessageCommand($this->messenger()->all()));
        return $response;
      }

      return TRUE;
    }

    $this->logDebug('Form submitted successfully');
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function validateCode($code) {
    $stored_code = $this->getCode();
    $this->logDebug('Validating code: @code against stored code: @stored', [
      '@code' => $code,
      '@stored' => $stored_code ?? 'not set'
    ]);
    return $code === $stored_code;
  }

  /**
   * {@inheritdoc}
   */
  public function getCode() {
    return $this->userData->get('tfa_sms', $this->uid, self::CODE_KEY);
  }

  /**
   * {@inheritdoc}
   */
  public function generateCode() {
    $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $this->userData->set('tfa_sms', $this->uid, self::CODE_KEY, $code);
    $this->logDebug('Generated new code: @code', ['@code' => $code]);
    return $code;
  }

  /**
   * {@inheritdoc}
   */
  public function sendCode() {
    $phone_number = $this->getUserPhoneNumber();
    if (!$phone_number) {
      $this->logDebug('Failed to send code: No phone number found for user');
      return FALSE;
    }

    // Check if enough time has passed since last send
    $last_send = (int) $this->userData->get('tfa_sms', $this->uid, self::LAST_SEND_KEY) ?: 0;
    if ($last_send > 0) {
      $time_passed = time() - $last_send;
      if ($time_passed < self::RESEND_INTERVAL) {
        $this->logDebug('Not enough time passed since last send (@time_passed s)', ['@time_passed' => $time_passed]);
        return FALSE;
      }
    }

    $code = $this->generateCode();
    $message = t('Your verification code is: @code', ['@code' => $code]);

    // Set last send time
    $this->userData->set('tfa_sms', $this->uid, self::LAST_SEND_KEY, time());
    
    $this->logDebug('Sending code to @phone', ['@phone' => $phone_number]);
    return $this->smsSender->send($phone_number, $message);
  }

  /**
   * Gets the user's phone number from the field_phone_number field.
   *
   * @return string|null
   *   The phone number or NULL if not found.
   */
  protected function getUserPhoneNumber() {
    $user = User::load($this->uid);
    if ($user && $user->hasField('field_phone_number') && !$user->get('field_phone_number')->isEmpty()) {
      $phone = $user->get('field_phone_number')->value;
      $this->logDebug('Found phone number: @phone', ['@phone' => $phone]);
      return $phone;
    }
    $this->logDebug('No phone number found for user');
    return NULL;
  }

  /**
   * Logs debug messages if debugging is enabled.
   *
   * @param string $message
   *   The message to log.
   * @param array $context
   *   The context variables for the message.
   */
  protected function logDebug($message, array $context = []) {
    if ($this->configFactory->get('tfa_sms.settings')->get('debug')) {
      $this->loggerFactory->get('tfa_sms_gateway')->debug($message, $context);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getContext() {
    return is_array($this->context) ? $this->context : [];
  }

  /**
   * {@inheritdoc}
   */
  public function getErrorMessages() {
    return $this->errorMessages;
  }

} 