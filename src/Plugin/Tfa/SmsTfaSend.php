<?php

namespace Drupal\tfa_sms\Plugin\Tfa;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\tfa\Plugin\TfaSendInterface;
use Drupal\tfa\Plugin\TfaBasePlugin;
use Drupal\tfa_sms\Service\SmsSender;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * @Tfa(
 *   id = "tfa_sms_send",
 *   label = @Translation("SMS TFA Send"),
 *   description = @Translation("TFA SMS Code Sending Plugin"),
 *   setupPluginId = "tfa_sms_setup",
 *   sendPluginId = "tfa_sms_send",
 *   fallbackPluginId = "tfa_sms_fallback",
 *   isFallback = FALSE
 * )
 */
class SmsTfaSend extends TfaBasePlugin implements TfaSendInterface, ContainerFactoryPluginInterface {

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
   * Constructs a new SmsTfaSend object.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, SmsSender $sms_sender, ConfigFactoryInterface $config_factory, LoggerChannelFactoryInterface $logger_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->smsSender = $sms_sender;
    $this->configFactory = $config_factory;
    $this->loggerFactory = $logger_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('tfa_sms.sender'),
      $container->get('config.factory'),
      $container->get('logger.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getForm(array $form, FormStateInterface $form_state) {
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['send'] = [
      '#type' => 'submit',
      '#value' => t('Send Code'),
      '#submit' => ['::sendCode'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array $form, FormStateInterface $form_state) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array $form, FormStateInterface $form_state) {
    return TRUE;
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

    $code = $this->generateCode();
    $message = t('Your verification code is: @code', ['@code' => $code]);

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
    $user = $this->getUser();
    if ($user->hasField('field_phone_number') && !$user->get('field_phone_number')->isEmpty()) {
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
      $this->loggerFactory->get('tfa_sms')->debug($message, $context);
    }
  }

} 