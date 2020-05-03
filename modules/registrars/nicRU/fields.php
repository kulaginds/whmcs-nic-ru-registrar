<?php

require_once __DIR__ . '/config.php';

// required fields
$NICRU_FIELDS = array(
    NICRU_FIELD_TYPE_FIZ => array(
        'birth-date',
        'country',
        'e-mail',
        'passport',
        'password',
        'person',
        'person-r',
        'phone',
        'p-addr',
    ),
    NICRU_FIELD_TYPE_IP  => array(
        'birth-date',
        'code',
        'country',
        'e-mail',
        'passport',
        'password',
        'person',
        'person-r',
        'phone',
        'p-addr',
    ),
    NICRU_FIELD_TYPE_JUR  => array(
        'address-r',
        'code',
        'country',
        'e-mail',
        'kpp',
        'org',
        'org-r',
        'password',
        'phone',
        'p-addr',
    ),
);

