<?php

namespace Drupal\tfa_sms\Service;

use Drupal\sms\Provider\SmsProviderInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\sms\Entity\SmsMessage;
use Drupal\sms\Entity\SmsGateway;

/**
 * Service for sending SMS messages.
 */
class SmsSender {

  /**
   * The SMS provider.
   *
   * @var \Drupal\sms\Provider\SmsProviderInterface
   */
  protected $smsProvider;

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
   * Constructs a new SmsSender object.
   *
   * @param \Drupal\sms\Provider\SmsProviderInterface $sms_provider
   *   The SMS provider.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(SmsProviderInterface $sms_provider, ConfigFactoryInterface $config_factory, LoggerChannelFactoryInterface $logger_factory) {
    $this->smsProvider = $sms_provider;
    $this->configFactory = $config_factory;
    $this->loggerFactory = $logger_factory;
  }

  /**
   * Sends an SMS message.
   *
   * @param string $phone
   *   The phone number to send the message to.
   * @param string $message
   *   The message to send.
   *
   * @return bool
   *   TRUE if the message was sent successfully, FALSE otherwise.
   */
  public function send($phone, $message) {
    $config = $this->configFactory->get('tfa_sms.settings');
    $debug = $config->get('debug');
    $logger = $this->loggerFactory->get('tfa_sms');

    if ($debug) {
      $logger->notice('Debug mode: SMS would be sent to @phone with message: @message', [
        '@phone' => $phone,
        '@message' => $message,
      ]);
      return TRUE;
    }

    try {
      // Get the default gateway from SMS Framework settings
      $sms_config = $this->configFactory->get('sms.settings');
      $default_gateway_id = $sms_config->get('default_gateway');
      
      if (empty($default_gateway_id)) {
        throw new \Exception('No default SMS gateway configured in SMS Framework settings.');
      }

      $gateway = SmsGateway::load($default_gateway_id);
      if (!$gateway) {
        throw new \Exception('Default SMS gateway not found. Please configure it in the SMS Framework settings.');
      }

      $sms_message = SmsMessage::create()
        ->addRecipient($phone)
        ->setMessage($message)
        ->setGateway($gateway);

      $this->smsProvider->send($sms_message);
      $logger->info('SMS sent successfully to @phone via @gateway', [
        '@phone' => $phone,
        '@gateway' => $default_gateway_id,
      ]);

      return TRUE;
    }
    catch (\Exception $e) {
      $logger->error('Failed to send SMS to @phone: @error', [
        '@phone' => $phone,
        '@error' => $e->getMessage(),
      ]);

      return FALSE;
    }
  }

} 