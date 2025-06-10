<?php return array(
    'root' => array(
        'name' => 'drupal/tfa_sms',
        'pretty_version' => 'dev-master',
        'version' => 'dev-master',
        'reference' => 'fb5848554ed602c231037e0ccbee9a42f1889030',
        'type' => 'drupal-module',
        'install_path' => __DIR__ . '/../../',
        'aliases' => array(),
        'dev' => true,
    ),
    'versions' => array(
        'drupal/tfa_sms' => array(
            'pretty_version' => 'dev-master',
            'version' => 'dev-master',
            'reference' => 'fb5848554ed602c231037e0ccbee9a42f1889030',
            'type' => 'drupal-module',
            'install_path' => __DIR__ . '/../../',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
        'twilio/sdk' => array(
            'pretty_version' => 'dev-main',
            'version' => 'dev-main',
            'reference' => 'b9c2b44392727bb22ee99d5972e7cbe361036fcc',
            'type' => 'library',
            'install_path' => __DIR__ . '/../twilio/sdk',
            'aliases' => array(
                0 => '9999999-dev',
            ),
            'dev_requirement' => false,
        ),
    ),
);
