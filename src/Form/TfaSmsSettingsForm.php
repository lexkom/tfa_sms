<?php

namespace Drupal\tfa_sms\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\sms\Provider\SmsProviderInterface;
use Drupal\sms\Entity\SmsMessage;
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

    $form['sender'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Sender name'),
      '#default_value' => $config->get('sender') ?? 'TFA',
      '#description' => $this->t('The name that will appear as the sender of the SMS.'),
      '#required' => TRUE,
    ];

    $form['phone_prefix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Phone number prefix'),
      '#default_value' => $config->get('phone_prefix') ?? '+1',
      '#description' => $this->t('The prefix to be added to phone numbers (e.g., +1 for US numbers).'),
      '#required' => TRUE,
    ];

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
      '#required' => TRUE,
    ];

    $form['test']['test_message'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Test message'),
      '#default_value' => $this->t('This is a test message from TFA SMS module.'),
      '#required' => TRUE,
    ];

    $form['test']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send test SMS'),
      '#submit' => ['::submitTestSms'],
      '#limit_validation_errors' => [['test_phone'], ['test_message']],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('tfa_sms.settings')
      ->set('sender', $form_state->getValue('sender'))
      ->set('phone_prefix', $form_state->getValue('phone_prefix'))
      ->set('debug', $form_state->getValue('debug'))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Submit handler for test SMS.
   */
  public function submitTestSms(array &$form, FormStateInterface $form_state) {
    $phone = $form_state->getValue('test_phone');
    $message = $form_state->getValue('test_message');
    $config = $this->config('tfa_sms.settings');

    // Add prefix if not already present
    if (!empty($config->get('phone_prefix')) && strpos($phone, $config->get('phone_prefix')) !== 0) {
      $phone = $config->get('phone_prefix') . $phone;
    }

    try {
      $sms_message = SmsMessage::create()
        ->addRecipient($phone)
        ->setMessage($message)
        ->setOption('provider', 'twilio');

      $this->smsProvider->send($sms_message);
      $this->messenger()->addStatus($this->t('Test SMS sent successfully to @phone.', ['@phone' => $phone]));
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('Failed to send test SMS: @error', ['@error' => $e->getMessage()]));
    }
  }

} 