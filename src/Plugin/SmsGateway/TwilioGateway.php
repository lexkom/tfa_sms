<?php

namespace Drupal\tfa_sms\Plugin\SmsGateway;

use Drupal\Core\Form\FormStateInterface;
use Drupal\sms\Plugin\SmsGatewayPluginBase;
use Drupal\sms\Message\SmsMessageInterface;
use Drupal\sms\Message\SmsMessageResultInterface;
use Drupal\sms\Message\SmsMessageResult;
use Drupal\sms\Message\SmsDeliveryReport;
use Twilio\Rest\Client;
use Twilio\Exceptions\TwilioException;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @SmsGateway(
 *   id = "twilio",
 *   label = @Translation("Twilio"),
 *   description = @Translation("Sends SMS messages using Twilio API."),
 * )
 */
class TwilioGateway extends SmsGatewayPluginBase {

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Constructs a new TwilioGateway instance.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->loggerFactory = \Drupal::service('logger.factory');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'account_sid' => '',
      'auth_token' => '',
      'from_number' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['account_sid'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Account SID'),
      '#description' => $this->t('Your Twilio Account SID.'),
      '#default_value' => $this->configuration['account_sid'],
      '#required' => TRUE,
    ];

    $form['auth_token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Auth Token'),
      '#description' => $this->t('Your Twilio Auth Token.'),
      '#default_value' => $this->configuration['auth_token'],
      '#required' => TRUE,
    ];

    $form['from_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('From Number'),
      '#description' => $this->t('The Twilio phone number to send messages from (in E.164 format, e.g. +1234567890).'),
      '#default_value' => $this->configuration['from_number'],
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);

    $account_sid = $form_state->getValue('account_sid');
    $auth_token = $form_state->getValue('auth_token');
    $from_number = $form_state->getValue('from_number');

    if (empty($account_sid)) {
      $form_state->setErrorByName('account_sid', $this->t('Account SID is required.'));
    }

    if (empty($auth_token)) {
      $form_state->setErrorByName('auth_token', $this->t('Auth Token is required.'));
    }

    if (empty($from_number)) {
      $form_state->setErrorByName('from_number', $this->t('From Number is required.'));
    }
    elseif (!preg_match('/^\+[1-9]\d{1,14}$/', $from_number)) {
      $form_state->setErrorByName('from_number', $this->t('From Number must be in E.164 format (e.g. +1234567890).'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    $this->configuration['account_sid'] = $form_state->getValue('account_sid');
    $this->configuration['auth_token'] = $form_state->getValue('auth_token');
    $this->configuration['from_number'] = $form_state->getValue('from_number');
  }

  /**
   * {@inheritdoc}
   */
  public function send(SmsMessageInterface $sms_message) {
    $result = new SmsMessageResult();
    if (!$this->loggerFactory) {
        throw new \Exception('Logger factory is not initialized');
      }
    $logger = $this->loggerFactory->get('tfa_sms_gateway');

    try {
      $logger->debug('Starting SMS sending process to @recipient', ['@recipient' => $sms_message->getRecipients()[0]]);
      $logger->debug('Creating SMS message for @recipient', ['@recipient' => $sms_message->getRecipients()[0]]);
      $logger->debug('Sending SMS message via Twilio provider');
      $logger->debug('Message content: @message', ['@message' => $sms_message->getMessage()]);
      $logger->debug('Configuration: @config', ['@config' => json_encode($this->configuration)]);

      if (empty($this->configuration['account_sid']) || empty($this->configuration['auth_token']) || empty($this->configuration['from_number'])) {
        throw new \Exception('Twilio configuration is incomplete. Please check Account SID, Auth Token and From Number settings.');
      }

      $client = new Client(
        $this->configuration['account_sid'],
        $this->configuration['auth_token']
      );

      $logger->debug('Twilio client created with Account SID: @sid', ['@sid' => $this->configuration['account_sid']]);
      $logger->debug('Using From Number: @number', ['@number' => $this->configuration['from_number']]);

      $message = $client->messages->create(
        $sms_message->getRecipients()[0],
        [
          'from' => $this->configuration['from_number'],
          'body' => $sms_message->getMessage(),
        ]
      );

      $logger->debug('Twilio API response: @response', ['@response' => json_encode($message->toArray())]);
      $logger->debug('Message SID: @sid', ['@sid' => $message->sid]);
      $logger->debug('Message status: @status', ['@status' => $message->status]);
      $logger->debug('Message error code: @code', ['@code' => $message->errorCode ?? 'none']);
      $logger->debug('Message error message: @message', ['@message' => $message->errorMessage ?? 'none']);

      $report = new SmsDeliveryReport();
      $report->setRecipient($sms_message->getRecipients()[0]);
      $report->setMessageId($message->sid);
      $report->setStatus($message->status);
      $report->setStatusMessage($message->errorMessage ?? '');
      $result->addReport($report);

      $logger->debug('SMS sent successfully to @recipient', ['@recipient' => $sms_message->getRecipients()[0]]);

    }
    catch (TwilioException $e) {
      $logger->error('Twilio API error: @error', ['@error' => $e->getMessage()]);
      $logger->error('Error code: @code', ['@code' => $e->getCode()]);
      $logger->error('Error trace: @trace', ['@trace' => $e->getTraceAsString()]);
      
      $report = new SmsDeliveryReport();
      $report->setRecipient($sms_message->getRecipients()[0]);
      $report->setStatus('error');
      $report->setStatusMessage($e->getMessage());
      $result->addReport($report);
    }
    catch (\Exception $e) {
      $logger->error('General error: @error', ['@error' => $e->getMessage()]);
      $logger->error('Error trace: @trace', ['@trace' => $e->getTraceAsString()]);
      
      $report = new SmsDeliveryReport();
      $report->setRecipient($sms_message->getRecipients()[0]);
      $report->setStatus('error');
      $report->setStatusMessage($e->getMessage());
      $result->addReport($report);
    }

    return $result;
  }

} 