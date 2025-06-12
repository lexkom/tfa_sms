<?php

namespace Drupal\tfa_sms\Plugin\Tfa;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\tfa\Plugin\TfaSetupInterface;
use Drupal\tfa\Plugin\TfaBasePlugin;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @Tfa(
 *   id = "tfa_sms_setup",
 *   label = @Translation("SMS TFA Setup"),
 *   description = @Translation("TFA SMS Setup Plugin"),
 *   setupPluginId = "tfa_sms_setup",
 *   sendPluginId = "tfa_sms_send",
 *   fallbackPluginId = "tfa_sms_fallback",
 *   isFallback = FALSE
 * )
 */
class SmsTfaSetup extends TfaBasePlugin implements TfaSetupInterface, ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function getSetupForm(array $form, FormStateInterface $form_state) {
    $form['phone_number'] = [
      '#type' => 'textfield',
      '#title' => t('Phone Number'),
      '#description' => t('Enter your phone number for SMS verification.'),
      '#required' => TRUE,
      '#default_value' => $this->getUserPhoneNumber(),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateSetupForm(array $form, FormStateInterface $form_state) {
    $phone = $form_state->getValue('phone_number');
    if (empty($phone)) {
      $form_state->setErrorByName('phone_number', t('Phone number is required.'));
      return FALSE;
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function submitSetupForm(array $form, FormStateInterface $form_state) {
    $user = $this->getUser();
    $phone = $form_state->getValue('phone_number');
    
    // Save phone number to user field.
    if ($user->hasField('field_phone_number')) {
      $user->set('field_phone_number', $phone);
      $user->save();
    }

    return TRUE;
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
      return $user->get('field_phone_number')->value;
    }
    return NULL;
  }

} 