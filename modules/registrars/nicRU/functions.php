<?php

require_once __DIR__ . '/../../../init.php';

use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Generate array of login and password
 * for auth in nic.ru
 * 
 * @param  string $login    partner login
 * @param  string $password partner password
 * @param  bool   $is_test  test flag
 * @return array            login and password
 */
function __nicRU_auth($login, $password, $is_test) {
    if ($is_test) {
        return array(
            'login'    => '370/NIC-REG/ADM',
            'password' => 'dogovor',
        );
    } else {
        return array(
            'login'    => $login . '/ADM',
            'password' => $password,
        );
    }
}

/**
 * Convert assoc array to nic.ru request text
 * @param  array  $params module params
 * @return string         request text
 */
function __nicRU_combine($params) {
    $result = '';

    foreach ($params as $key => $value) {
        if (is_array($value)) {
            foreach ($value as $v) {
                $result .= $key . ':'
                           . iconv('UTF-8', 'KOI8-R', $v) . "\n";
            }
        } else {
            $result .= $key . ':' . iconv('UTF-8', 'KOI8-R', $value)
                       . "\n";
        }
    }

    return $result;
}

/**
 * Make query to nic.ru api
 * 
 * @param  array  $auth      array with login and pass for nic.ru
 * @param  string $request   request field of request text
 * @param  string $operation operation field of request text
 * @param  array  $params    module params
 * @param  string $subject   client login for nic.ru
 * @return array             result array of
 *                           function __nicRU_parse_query
 */
function __nicRU_query(
            $auth,
            $request,
            $operation,
            $params,
            $subject = NULL
        ) {
    $data  = __nicRU_combine($auth);
    $data .= 'lang:ru' . "\n";
    $data .= 'request:' . $request . "\n";
    $data .= 'operation:' . $operation . "\n";
    $data .= 'request-id:' . date('YmdGis') . '.'
             . getmypid() . '@ruskyhost.ru' . "\n";
    
    if (!empty($subject)) {
        $data .= 'subject-contract:' . $subject . "\n";
    }

    foreach ($params as $key => $value) {
        foreach ($value as $object) {
            $data .= "\n";
            $data .= '[' . $key . ']' . "\n";
            $data .= __nicRU_combine($object);
        }
    }

    $curl = curl_init();

    if (false === $curl) {
        echo 'invalid curl';
        die();
    }

    $data = array('SimpleRequest' => $data);

    curl_setopt($curl, CURLOPT_URL, 'https://www.nic.ru/dns/dealer');
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));

    unset($data);

    $out  = curl_exec($curl);
    $code = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);

    curl_close($curl);

    $out = iconv('KOI8-R', 'UTF-8', $out);

    if (strlen($out) > 0) {
        return __nicRU_parse_query($out);
    } else {
        return array(
            'status' => $code,
            'errors' => array('empty result'),
        );
    }
}

/**
 * Parse nic.ru api response on assoc array
 * @param  string $text nic.ru api response
 * @return array        'status' - response code
 *                      'request-id' - request id
 *                      request objects
 *                      fields
 */
function __nicRU_parse_query($text) {
    $sep           = "\r\n";
    $line          = strtok($text, $sep);
    $result        = array();
    $start_body    = false;
    $request_index = 0;
    $request       = NULL;
    $has_error     = false;

    do {
        if (preg_match('/\[([a-z-]+)\]/', $line, $matches)) {
            $request = $matches[1];

            if (isset($result[$request])) {
                $request_index++;
            } else {
                $request_index = 0;
            }

            continue;
        }

        $div_by_colon = preg_split('/:\s?/', $line);

        if (is_array($div_by_colon)) {
            $param_name  = $div_by_colon[0];
            $param_value = array_key_exists(1, $div_by_colon)
                           ? $div_by_colon[1] : NULL;

            switch ($param_name) {
                case 'State':
                    $result['status'] = (int)substr($param_value, 0, 3);

                    if ($result['status'] >= 400) {
                        $has_error = true;
                    }
                    break;
                case 'request-id':
                    $result[$param_name] = $param_value;
                    $start_body          = true;
                    break;

                default:
                    if (empty($request)) {
                        $result[$param_name] = $param_value;
                    } else {
                        if ($request == 'errors') {
                            $result[$request][] = $line;
                            break;
                        }

                        if (isset($result[$request]
                                         [$request_index]
                                         [$param_name])) {
                            if (is_array($result[$request]
                                                [$request_index]
                                                [$param_name])) {
                                $result[$request]
                                       [$request_index]
                                       [$param_name] = array_merge(
                                    $result[$request]
                                           [$request_index]
                                           [$param_name],
                                    array($param_value)
                                );
                            } else {
                                $result[$request]
                                       [$request_index]
                                       [$param_name] = array(
                                    $result[$request]
                                           [$request_index]
                                           [$param_name],
                                    $param_value
                                );
                            }
                        } else {
                            $result[$request]
                                   [$request_index]
                                   [$param_name] = $param_value;
                        }
                    }
                    break;
            }
        }
    } while (false != ($line = strtok($sep)));

    return $result;
}

/**
 * Return anketa for client from database
 * 
 * @param  int    $userid user id in WHMCS
 * @return string         client login for nic.ru
 */
function __nicRU_get_anketa($userid) {
    $result = Capsule::table('module_nic_ru_anketa')
        ->select('anketa')
        ->where('userid', '=', (int)$userid)
        ->get();

    $result = json_decode(json_encode($result), true);

    if (count($result) == 0) {
        return NULL;
    }

    $row    = $result[0];
    $anketa = $row['anketa'];

    return $anketa;
}

/**
 * Put anketa for client with password to database
 * 
 * @param  int    $userid   client id in WHMCS
 * @param  string $anketa   nic.ru client login
 * @param  string $password nic.ru client password
 */
function __nicRU_put_anketa($userid, $anketa, $password) {
    Capsule::table('module_nic_ru_anketa')
        ->insert(
            array(
                'userid' => $userid,
                'anketa' => $anketa,
                'pass'   => $password,
            ),
        );
}

/**
 * Return anketa for domain from database
 * 
 * @param  int    $domainid user id in WHMCS
 * @return string           client login for nic.ru
 */
function __nicRU_get_domain_anketa($domainid) {
    $result = Capsule::table('module_nic_ru')
        ->select('anketa')
        ->where('domain_id', '=', (int)$domainid)
        ->get();

    $result = json_decode(json_encode($result), true);

    if (count($result) == 0) {
        return NULL;
    }

    $row    = $result[0];
    $anketa = $row['anketa'];

    return $anketa;
}

/**
 * Put anketa for domain with password to database
 * 
 * @param  int    $domainid domain id in WHMCS
 * @param  string $anketa   nic.ru client login
 * @param  string $password nic.ru client password
 */
function __nicRU_put_domain_anketa($domainid, $anketa, $password) {
    Capsule::table('module_nic_ru')
        ->insert(
            array(
                'domain_id' => $domainid,
                'anketa' => $anketa,
                'pass'   => $password,
            ),
        );
}

function __nicRU_get_anketa_by_domainid($userid) {
	$result = Capsule::table('tbldomains')
        ->select('module_nic_ru_anketa.anketa', 'module_nic_ru_anketa.pass')
        ->join('module_nic_ru_anketa', 'module_nic_ru_anketa.userid', '=', 'tbldomains.userid')
        ->where('tbldomains.id', '=', (int)$userid)
        ->get();

    $result = json_decode(json_encode($result), true);

    if (count($result) == 0) {
        return NULL;
    }

    $row    = $result[0];
    $anketa = $row['anketa'];
    $pass   = $row['pass'];

    return array($anketa, $pass);
}

/**
 * Return field value from client customfields
 * @param  array  $params module params
 * @param  string $id     custom field id
 * @return string         value of field
 */
function __nicRU_get_field_by_id($params, $id) {
    $fields = $params['customfields'];
    $value  = NULL;

    foreach ($fields as $field) {
        if ($id == $field['id']) {
            $value = trim($field['value']);
            break;
        }
    }

    return $value;
}

/**
 * Check anketa params for specific $type
 * 
 * @param  string $type   type of client WHMCS
 * @param  array  $params module params
 * @return mixed          NULL - if ok; string - message
 */
function __nicRU_check_params($type, $params, $NICRU_FIELDS) {
    if (!in_array($type, array(
                NICRU_FIELD_TYPE_FIZ,
                NICRU_FIELD_TYPE_JUR,
                NICRU_FIELD_TYPE_IP))) {
        return 'Неизвестный тип клиента';
    }

    $fields = $NICRU_FIELDS[$type];
    
    //file_put_contents(__DIR__.'/debug.txt', json_encode($params));

    // check anketa fields
    foreach ($fields as $field) {
        switch ($field) {
            case 'birth-date':
                if (!preg_match('/^[0-9]{2}.[0-9]{2}.[0-9]{4}$/',
                            __nicRU_get_field_by_id(
                                $params,
                                NICRU_FIELD_BIRTHDAY
                            ))) {
                    return 'Неверный формат даты рождения (должно'
                           . ' быть ДД.ММ.ГГГГ), не должно быть пусто';
                }
                break;
            case 'country':
                if (!preg_match('/^[A-Z]{2}$/', trim($params['countrycode']))) {
                    return 'Неверный код страны (должно быть например'
                           . ' RU)';
                }
                break;
            case 'e-mail':
                if (strlen(trim($params['email'])) > 256) {
                    return 'E-mail слишком длинный (разрешено до'
                           . ' 256 символов)';
                }
                break;
            case 'passport':
                $ser_num = trim(__nicRU_get_field_by_id(
                    $params,
                    NICRU_FIELD_PASSPORT_SER_NUM
                ));

                if (empty($ser_num)) {
                    return 'Серия и номер паспорта не должны быть'
                           . ' пустыми';
                }

                $passport_who = trim(__nicRU_get_field_by_id(
                    $params,
                    NICRU_FIELD_PASSPORT_WHO
                ));

                if (empty($passport_who)) {
                    return 'Поле "Кем выдан паспорт" не должно быть'
                           . ' пустым';
                }

                if (!preg_match('/[0-9]{2}.[0-9]{2}.[0-9]{4}/',
                            __nicRU_get_field_by_id(
                                $params,
                                NICRU_FIELD_PASSPORT_DATE
                            ))) {
                    return 'Неверный формат даты выдачи паспорта'
                           . ' (должно быть ДД.ММ.ГГГГ)';
                }
                break;
            case 'password':
                $result = localAPI('GetClientPassword', array(
                    'userid' => $params['userid'],
                ));

                if (!array_key_exists('password', $result)) {
                    return $result['message'];
                }

                $password = $result['password'];

                if (!preg_match('/^[a-z0-9]{1,30}$/i', $password)) {
                    return 'Мин. длина пароля: 1 символ.'
                           . ' Макс.: 30. Может содержать латинские'
                           . ' буквы разного регистра и цифры.';
                }
                break;
            case 'person':
                if (!preg_match('/^[a-z]{1,30}$/i', trim($params['firstname']))) {
                    return 'Поле "Имя" должно быть заполнено'
                           . ' латинскими буквами и не быть пустым';
                }

                if (!preg_match('/^[a-z]{1,30}$/i', trim($params['lastname']))) {
                    return 'Поле "Фамилия" должно быть заполнено'
                           . ' латинскими буквами и не быть пустым';
                }

                if (!preg_match('/^[a-z]{1,30}$/i',
                        __nicRU_get_field_by_id(
                            $params,
                            NICRU_FIELD_MIDDLE_NAME
                        ))) {
                    return 'Поле "Отчество" должно быть заполнено'
                           . ' латинскими буквами и не быть пустым';
                }
                break;
            case 'person-r':
                if (!preg_match('/^[а-яё]{1,30}$/iu',
                        __nicRU_get_field_by_id(
                            $params,
                            NICRU_FIELD_FIRST_NAME_R
                        ))) {
                    return 'Поле "Имя (рус.)" должно быть заполнено'
                           . ' кириллицей и не быть пустым';
                }

                if (!preg_match('/^[а-яё]{1,30}$/iu',
                        __nicRU_get_field_by_id(
                            $params,
                            NICRU_FIELD_LAST_NAME_R
                        ))) {
                    return 'Поле "Фамилия (рус.)" должно быть заполнено'
                           . ' кириллицей и не быть пустым';
                }

                if (!preg_match('/^[а-яё]{1,30}$/iu',
                        __nicRU_get_field_by_id(
                            $params,
                            NICRU_FIELD_MIDDLE_NAME_R
                        ))) {
                    return 'Поле "Отчество (рус.)" должно быть заполнено'
                           . ' кириллицей и не быть пустым';
                }
                break;
            case 'phone':
                if (!preg_match('/^[0-9\s\(\)]{11,256}$/iu',
                        trim($params['phonenumber']))) {
                    return 'Номер телефона может содержать цифры, скобки'
                           . ' и пробелы должен быть длинее 11 символов';
                }
                break;
            case 'p-addr':
                if (strlen(__nicRU_get_field_by_id(
                        $params,
                        NICRU_FIELD_P_ADDR
                    )) == 0) {
                    return 'Нужно заполнить почтовый адрес';
                }
                break;
            case 'code':
                $lim = 12;

                if ($type == NICRU_FIELD_TYPE_JUR) {
                    $lim = 10;

                    // not resident of Russia
                    if ($params['countrycode'] != 'RU') {
                        break;
                    }
                }

                if (!preg_match(
                        '/^[0-9]{' . $lim . '}$/',
                        __nicRU_get_field_by_id(
                            $params,
                            NICRU_FIELD_CODE
                        ))) {
                    return 'ИНН должен быть ' . $lim . '-значным'
                           . ' числом';
                }
                break;
            case 'address-r':
                $address1 = trim($params['address1']);

                if (empty($address1)) {
                    return 'Поле "Адрес" не может быть пустым';
                }

                $city = trim($params['city']);

                if (empty($city)) {
                    return 'Поле "Город" не может быть пустым';
                }

                $postcode = trim($params['postcode']);

                if (empty($postcode)) {
                    return 'Поле "Индекс" не может быть пустым';
                }

                $countrycode = trim($params['countrycode']);

                if (empty($countrycode)) {
                    return 'Поле "Страна" не может быть пустым';
                }
                break;
            case 'kpp':
                // not resident of Russia
                if ($params['countrycode'] != 'RU') {
                    break;
                }

                if (!preg_match('/^[0-9]{9}$/', __nicRU_get_field_by_id(
                        $params,
                        NICRU_FIELD_KPP
                    ))) {
                    return 'КПП должен быть 9-значным числом и не быть'
                           . ' пустым';
                }
                break;
            case 'org':
                $len = strlen(trim(__nicRU_get_field_by_id(
                    $params,
                    NICRU_FIELD_ORG_NAME
                )));

                if ($len == 0) {
                    return 'Наименование организации (англ.) не должно'
                           . ' быть пустым';
                }

                if ($len > 100) {
                    return 'Наименование организации (англ.) слишком'
                           . ' длинное (макс. 100 символов)';
                }
                break;
            case 'org-r':
                $len = mb_strlen(trim(__nicRU_get_field_by_id(
                    $params,
                    NICRU_FIELD_ORG_NAME_R
                )));

                if ($len == 0) {
                    return 'Полное наименование организации не может'
                           . ' быть пустым';
                }

                if ($len > 256) {
                    return 'Полное наименование организации слишком'
                           . ' длинное (макс. 256 символов)';
                }
                break;
        }
    }

    return NULL;
}

/**
 * Prepare anketa fields for specific $type
 * 
 * @param  string $type   type of client WHMCS
 * @param  array  $params module params
 * @return array          fields assoc array
 */
function __nicRU_prepare_fields($type, $params, $NICRU_FIELDS) {
    $fields = $NICRU_FIELDS[$type];
    $result = array();

    // check anketa fields
    foreach ($fields as $field) {
        switch ($field) {
            case 'birth-date':
                $result[$field] = __nicRU_get_field_by_id(
                    $params,
                    NICRU_FIELD_BIRTHDAY
                );
                break;
            case 'country':
                $result[$field] = $params['countrycode'];
                break;
            case 'e-mail':
                $result[$field] = trim($params['email']);
                break;
            case 'passport':
                // formatting
                $result[$field] = array(
                    __nicRU_get_field_by_id(
                        $params,
                        NICRU_FIELD_PASSPORT_SER_NUM
                    ) . ' выдан '
                    . __nicRU_get_field_by_id(
                        $params,
                        NICRU_FIELD_PASSPORT_WHO
                    ) . ', '
                    . __nicRU_get_field_by_id(
                        $params,
                        NICRU_FIELD_PASSPORT_DATE
                    ),
                    'зарегистрирован по адресу: '
                    . __nicRU_get_field_by_id(
                        $params,
                        NICRU_FIELD_P_ADDR
                    )
                );
                break;
            case 'password':
                $res = localAPI('GetClientPassword', array(
                    'userid' => $params['userid'],
                ));

                $result[$field] = $res['password'];
                break;
            case 'person':
                // formatting
                $person = trim($params['firstname']) . ' ';
                $person .= ucfirst(
                            substr(
                                trim(__nicRU_get_field_by_id(
                                        $params,
                                        NICRU_FIELD_MIDDLE_NAME
                                    ))
                            , 0, 1)
                           ) . ' ';
                $person .= trim($params['lastname']);

                $result[$field] = $person;
                break;
            case 'person-r':
                // formatting
                $person = __nicRU_get_field_by_id(
                    $params,
                    NICRU_FIELD_LAST_NAME_R
                ) . ' ';
                $person .= __nicRU_get_field_by_id(
                    $params,
                    NICRU_FIELD_FIRST_NAME_R
                ) . ' ';
                $person .= __nicRU_get_field_by_id(
                    $params,
                    NICRU_FIELD_MIDDLE_NAME_R
                );

                $result[$field] = $person;
                break;
            case 'phone':
                $result[$field] = '+' . trim($params['phonenumber']);
                break;
            case 'p-addr':
                $result[$field] = __nicRU_get_field_by_id(
                    $params,
                    NICRU_FIELD_P_ADDR
                );
                break;
            case 'code':
                $result[$field] = __nicRU_get_field_by_id(
                    $params,
                    NICRU_FIELD_CODE
                );
                break;
            case 'address-r':
                // formatting
                $addr = trim($params['postcode']) . ' ';
                $addr .= trim($params['city']) . ', ';
                
                $state = trim($params['state']);

                if (!empty($state)) {
                    $addr .= $state . ', ';
                }

                $addr .= trim($params['address1']);

                $result[$field] = $addr;
                break;
            case 'kpp':
                $result[$field] = __nicRU_get_field_by_id(
                    $params,
                    NICRU_FIELD_KPP
                );
                break;
            case 'org':
                $result[$field] = __nicRU_get_field_by_id(
                    $params,
                    NICRU_FIELD_ORG_NAME
                );
                break;
            case 'org-r':
                $result[$field] = __nicRU_get_field_by_id(
                    $params,
                    NICRU_FIELD_ORG_NAME_R
                );
                break;
        }
    }

    return $result;
}

/**
 * Make request to nic.ru for creating anketa
 * 
 * @param  string $type   type of client WHMCS
 * @param  array  $params module params
 * @param  array  $auth   array with login and pass for nic.ru
 * @return mixed          string - anketa;
 *                        array('error' => 'message') - if error
 */
function __nicRU_create_anketa($type, $params, $auth, $NICRU_FIELDS) {
    $fields = __nicRU_prepare_fields($type, $params, $NICRU_FIELDS);

    $fields['contract-type'] = 'PRS';

    if ($type == NICRU_FIELD_TYPE_JUR) {
        $fields['contract-type'] = 'ORG';
    }

    $fields['currency-id'] = RUR;

    $query = __nicRU_query($auth, 'contract', 'create', array(
        'contract' => array($fields),
    ));

    if ($query['status'] >= 400) {
        return array(
            'error' => implode('<br>', $query['errors']),
        );
    }

    __nicRU_put_anketa(
        $params['userid'],
        $query['login'],
        $fields['password']
    );

    __nicRU_put_domain_anketa(
        $params['domainid'],
        $query['login'],
        $fields['password']
    );

    return $query['login'];
}

/**
 * Make request to nic.ru for register domain
 * 
 * @param  string $anketa client login for nic.ru
 * @param  string $domain domain name
 * @return array          'success' => true or 'error' => 'message'
 */
function __nicRU_register_domain($anketa, $domain, $auth, $ns) {
    // return array('error' => 'тестирование');
    $query = __nicRU_query($auth, 'order', 'create', array(
        'order-item' => array(
            array(
                'service' => 'domain',
                'action'  => 'new',
                'domain'  => $domain,
                'nserver' => $ns,
            ),
        ),
    ), $anketa);

    if ($query['status'] >= 400) {
        return array('error' => implode('<br>', $query['errors']));
    }

    return array('success' => true);
}
