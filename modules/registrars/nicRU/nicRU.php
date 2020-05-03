<?php

/**
 * WHMCS Nic.ru registrar module
 *
 * @see https://github.com/kulaginds/whmcs-nic-ru-registrar
 *
 * @author Dmitry Kulagin <kulaginds@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3
 */

define('NICRU_LOGIN', 'nicru_login');
define('NICRU_PASSWORD', 'nicru_password');
define('NICRU_TEST', 'nicru_test');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

/**
 * Return config array to WHMCS
 * @return array config fields
 */
function nicRU_getConfigArray() {
    $configarray = array(
        NICRU_LOGIN => array (
            'FriendlyName' => 'Nic.ru login',
            'Type'         => 'text',
            'Size'         => '15',
            'Description'  => 'Example: 123/NIC-REG',
        ),
        NICRU_PASSWORD => array (
            'FriendlyName' => 'Nic.ru password',
            'Type'         => 'password',
            'Size'         => '25',
            'Description'  => 'Example: mysuperpass',
        ),
        NICRU_TEST => array (
            'FriendlyName' => 'Test mode',
            'Type'         => 'yesno',
            'Description'  => 'Tick for testing',
        ),
    );

    return $configarray;
}

/**
 * Register domain in nic.ru
 * 
 * @param  array $params module params
 * @return array         'success' => true or 'error' => 'message'
 */
function nicRU_RegisterDomain($params) {
    require_once __DIR__ . '/fields.php';

    $auth   = __nicRU_auth(
        $params[NICRU_LOGIN],
        $params[NICRU_PASSWORD],
        $params[NICRU_TEST]
    );
    $anketa = __nicRU_get_anketa($params['userid']);

    if (empty($anketa)) {
        $type  = __nicRU_get_field_by_id($params, NICRU_FIELD_TYPE);
        $error = __nicRU_check_params($type, $params, $NICRU_FIELDS);

        if (!empty($error)) {
            return array(
                'error' => $error,
            );
        }

        $data = __nicRU_create_anketa(
            $type,
            $params,
            $auth,
            $NICRU_FIELDS
        );

        if (is_string($data)) {
            $anketa = $data;
        } else {
            return array(
                'error' => $data['error'],
            );
        }
    }

    $ns = array(
        $params['ns1'],
        $params['ns2'],
        $params['ns3'],
        $params['ns4'],
        $params['ns5'],
    );

    $result = __nicRU_register_domain(
        $anketa,
        $params['domainname'],
        $auth,
        $ns
    );

    return $result;
}

/**
 * Cap of TransferDomain function
 * 
 * @param  array $params module params
 * @return array         error message
 */
function nicRU_TransferDomain($params) {
    return array(
        'error' => 'Это действие нужно выполнять вручную',
    );
}

/**
 * Send renew request to nic.ru for
 * specific domain name
 * 
 * @param  array $params module params
 * @return array         success or error
 */
function nicRU_RenewDomain($params) {
    $auth = __nicRU_auth(
        $params[NICRU_LOGIN],
        $params[NICRU_PASSWORD],
        $params[NICRU_TEST]
    );

    $anketa = __nicRU_get_domain_anketa($params['domainid']);

    if (empty($anketa)) {
        list($anketa, $pass) = __nicRU_get_anketa_by_domainid(
            $params['domainid']
        );

        if (empty($anketa)) {
            return array(
                'error' => 'У домена нет анкеты в nic.ru',
            );
        }

        __nicRU_put_domain_anketa(
            $params['domainid'],
            $anketa,
            $pass
        );
    }

    $query = __nicRU_query($auth, 'order', 'create', array(
        'order-item' => array(
            array(
                'action'  => 'prolong',
                'service' => 'domain',
                'domain'  => $params['domainname'],
                'prolong' => $params['regperiod'],
            ),
        ),
    ), $anketa);

    if ($query['status'] >= 400) {
        return array('error' => implode('<br>', $query['errors']));
    }

    return array('success' => true);
}

/**
 * Return nameservers from nic.ru
 * by domain name
 * 
 * @param  array $params module params
 * @return array         success or error
 */
function nicRU_GetNameservers($params) {
    $auth = __nicRU_auth(
        $params[NICRU_LOGIN],
        $params[NICRU_PASSWORD],
        $params[NICRU_TEST]
    );

    $anketa = __nicRU_get_domain_anketa($params['domainid']);
    $query  = __nicRU_query($auth, 'domain', 'search', array(
        'domain' => array(
            array(
                'domain'  => $params['domainname'],
            ),
        ),
    ));

    if ($query['status'] >= 400) {
        return array('error' => implode('<br>', $query['errors']));
    }

    $result = array(
        'success' => true,
        'ns1'     => $query['domain'][0]['nameservers'][0],
        'ns2'     => $query['domain'][0]['nameservers'][1],
        'ns3'     => $query['domain'][0]['nameservers'][2],
        'ns4'     => $query['domain'][0]['nameservers'][3],
        'ns5'     => $query['domain'][0]['nameservers'][4],
    );

    return $result;
}

/**
 * Save nameservers in nic.ru
 * 
 * @param  array $params module params
 * @return array         success or error
 */
function nicRU_SaveNameservers($params) {
    $auth = __nicRU_auth(
        $params[NICRU_LOGIN],
        $params[NICRU_PASSWORD],
        $params[NICRU_TEST]
    );

    $anketa = __nicRU_get_domain_anketa($params['domainid']);
    $query  = __nicRU_query($auth, 'order', 'create', array(
        'order-item' => array(
            array(
                'service' => 'domain',
                'action'  => 'update',
                'domain'  => $params['domainname'],
                'nserver' => array(
                    $params['ns1'],
                    $params['ns2'],
                    $params['ns3'],
                    $params['ns4'],
                    $params['ns5'],
                ),
            ),
        ),
    ), $anketa);

    if ($query['status'] >= 400) {
        return array('error' => implode('<br>', $query['errors']));
    }

    return array('success' => true);
}

/**
 * Cap of GetContactDetails function
 * @param  array $params module params
 * @return array         error
 */
function nicRU_GetContactDetails($params) {
    return array('error' => 'Это действие нужно выполнять вручную');
}

/**
 * Cap of SaveContactDetails function
 * @param  array $params module params
 * @return array         error
 */
function nicRU_SaveContactDetails($params) {
    return array('error' => 'Это действие нужно выполнять вручную');
}
