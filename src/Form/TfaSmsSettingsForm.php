<?php

namespace Drupal\tfa_sms\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\sms\Provider\SmsProviderInterface;
use Drupal\sms\Entity\SmsMessage;
use Drupal\sms\Entity\SmsGateway;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure TFA SMS settings.
 */
class TfaSmsSettingsForm extends ConfigFormBase {

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
   * Constructs a TfaSmsSettingsForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\sms\Provider\SmsProviderInterface $sms_provider
   *   The SMS provider.
   */
  public function __construct(ConfigFactoryInterface $config_factory, SmsProviderInterface $sms_provider) {
    parent::__construct($config_factory);
    $this->smsProvider = $sms_provider;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('sms.provider')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'tfa_sms_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['tfa_sms.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('tfa_sms.settings');

    $form['debug'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable debug logging'),
      '#description' => $this->t('When enabled, SMS messages will be logged instead of being sent.'),
      '#default_value' => $config->get('debug'),
    ];

    $form['test'] = [
      '#type' => 'details',
      '#title' => $this->t('Test SMS sending'),
      '#open' => TRUE,
    ];

    $form['test']['test_phone'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Test phone number'),
      '#description' => $this->t('Enter a phone number to test SMS sending.'),
    ];

    $form['test']['test_message'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Test message'),
      '#default_value' => $this->t('This is a test message from TFA SMS module.'),
    ];

    $form['test']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send test SMS'),
      '#submit' => ['::submitTestSms'],
      '#validate' => ['::validateTestSms'],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('tfa_sms.settings')
      ->set('debug', $form_state->getValue('debug'))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Validate test SMS fields.
   */
  public function validateTestSms(array &$form, FormStateInterface $form_state) {
    $phone = $form_state->getValue('test_phone');
    $message = $form_state->getValue('test_message');

    if (empty($phone)) {
      $form_state->setErrorByName('test_phone', $this->t('Phone number is required for test SMS.'));
    }

    if (empty($message)) {
      $form_state->setErrorByName('test_message', $this->t('Message is required for test SMS.'));
    }
  }

  /**
   * Submit handler for test SMS.
   */
  public function submitTestSms(array &$form, FormStateInterface $form_state) {
    $phone = $form_state->getValue('test_phone');
    $message = $form_state->getValue('test_message');
    $config = $this->config('tfa_sms.settings');
    $debug = $config->get('debug');

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

      if ($debug) {
        $this->messenger()->addStatus($this->t('Debug mode: SMS not actually sent to @phone. Message would be: @message', [
          '@phone' => $phone,
          '@message' => $message,
        ]));
        return;
      }

      $sms_message = SmsMessage::create()
        ->addRecipient($phone)
        ->setMessage($message)
        ->setGateway($gateway);

      $this->smsProvider->send($sms_message);
      $this->messenger()->addStatus($this->t('Test SMS sent successfully to @phone.', ['@phone' => $phone]));
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('Failed to send test SMS: @error', ['@error' => $e->getMessage()]));
    }
  }

} 