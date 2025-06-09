<?php

namespace Drupal\tfa_sms\Service;

use Drupal\sms\Provider\SmsProviderInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\sms\Entity\SmsMessage;

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
    $logger = $this->loggerFactory->get('tfa_sms_gateway');

    // Log the attempt.
    $logger->debug('Starting SMS sending process to @phone', [
      '@phone' => $phone,
      '@message' => $message,
    ]);

    // If debug mode is enabled, just log and return success.
    if ($debug) {
      $logger->debug('Debug mode: SMS not actually sent to @phone. Message would be: @message', [
        '@phone' => $phone,
        '@message' => $message,
      ]);
      return TRUE;
    }

    try {
      // Create and send the SMS message.
      $logger->debug('Creating SMS message for @phone', ['@phone' => $phone]);
      
      $sms_message = SmsMessage::create()
        ->addRecipient($phone)
        ->setMessage($message)
        ->setOption('provider', 'twilio');

      $logger->debug('Sending SMS message via Twilio provider');
      $this->smsProvider->send($sms_message);

      $logger->debug('SMS sent successfully to @phone', [
        '@phone' => $phone,
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