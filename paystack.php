<?php


/**
 * PHP Mikrotik Billing (https://github.com/hotspotbilling/phpnuxbill/)
 *
 * Payment Gateway paystack.com
 *
 * created by @foculinkstech
 *
 **/


function paystack_validate_config()
{
    global $config;
    if (empty($config['paystack_secret_key'])) {
        Message::sendTelegram("paystack payment gateway not configured");
        r2(U . 'order/package', 'w', Lang::T("Admin has not yet setup paystack payment gateway, please tell admin"));
    }
}

function paystack_show_config()
{
    global $ui, $config;
    $ui->assign('_title', 'Paystack - Payment Gateway');
    $config['paystack_channel'] = isset($config['paystack_channel']) ? explode(',', $config['paystack_channel']) : [];
    $ui->assign('_c', $config);
    $ui->display('paystack.tpl');
}


function paystack_save_config()
{
    global $admin;
    $paystack_secret_key = _post('paystack_secret_key');
    $paystack_currency = _post('paystack_currency');
    $paystack_channel = isset($_POST['paystack_channel']) && is_array($_POST['paystack_channel'])
        ? $_POST['paystack_channel']
        : [];
    $settings = [
        'paystack_secret_key' => $paystack_secret_key,
        'paystack_currency' => $paystack_currency,
        'paystack_channel' => implode(',', $paystack_channel)
    ];

    // Update or insert settings in the database
    foreach ($settings as $key => $value) {
        $d = ORM::for_table('tbl_appconfig')->where('setting', $key)->find_one();
        if ($d) {
            $d->value = $value;
            $d->save();
        } else {
            $d = ORM::for_table('tbl_appconfig')->create();
            $d->setting = $key;
            $d->value = $value;
            $d->save();
        }
    }

    _log('[' . $admin['username'] . ']: ' . Lang::T('Settings Saved Successfully'), $admin['user_type']);
    r2(U . 'paymentgateway/paystack', 's', Lang::T('Settings Saved Successfully'));
}
function paystack_create_transaction($trx, $user)
{
    global $config;

    $txref = uniqid('trx');
    $total = $trx['price'] * 100; 
    
    $json = [
        'reference' => $txref,
        'amount' => $total,
        'currency' => $config['paystack_currency'],
        'channels' => explode(',', $config['paystack_channel']),
        'email' => !empty($user['email']) ? $user['email'] : $user['username'] . '@' . $_SERVER['HTTP_HOST'],
        'customer' => [
            'firstname' => $user['fullname'],
            'phone' => $user['phonenumber']
        ],
        'meta' => [
            'price' => $trx['price'],
            'userid' => $user['id'],
            'planid' => $trx['plan_id'],
            'router' => $trx['routers']
        ],
        'customizations' => [
            'title' => $trx['plan_name'],
            'description' => $trx['plan_name']
        ],
        'callback_url' => U . 'order/view/' . $trx['id'] . '/trx'
    ];

    $response = Http::postJsonData(
        'https://api.paystack.co/transaction/initialize',
        $json,
        [
            'Authorization: Bearer ' . $config['paystack_secret_key'],
            'Cache-Control: no-cache'
        ]
    );

    $result = json_decode($response, true);
    if (!$result || !isset($result['status'])) {
        Message::sendTelegram("Paystack API response error:\n\n" . json_encode($response, JSON_PRETTY_PRINT));
        r2(U . 'order/package', 'e', Lang::T("Failed to create transaction. Paystack API error."));
    }

    if ($result['status'] === false) {
        Message::sendTelegram("Paystack payment initialization failed\n\n" . json_encode($result, JSON_PRETTY_PRINT));
        r2(U . 'order/package', 'e', Lang::T("Failed to create transaction.\n" . $result['message']));
    }

    $d = ORM::for_table('tbl_payment_gateway')
        ->where('username', $user['username'])
        ->where('status', 1)
        ->find_one();

    if (!$d) {
        r2(U . 'order/package', 'e', Lang::T("Failed to find payment gateway record for the user."));
    }

    $d->gateway_trx_id = $result['data']['reference'];
    $d->pg_url_payment = $result['data']['authorization_url'];
    $d->pg_request = json_encode($result);
    $d->expired_date = date('Y-m-d H:i:s', strtotime("+6 HOUR"));
    $d->save();

    header('Location: ' . $result['data']['authorization_url']);
    exit();
}


function paystack_payment_notification()
{
    global $config;

    if (strtoupper($_SERVER['REQUEST_METHOD']) != 'POST') {
        // Return JSON response for invalid request method
        header('Content-Type: application/json');
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['error' => 'Bad Request: Not a valid webhook.']);
        exit();
    }

    $paymentDetails = @file_get_contents("php://input");
    $headers = getallheaders();
    file_put_contents("pages/paystack-webhook.html", "<pre>$paymentDetails</pre>", FILE_APPEND);
    file_put_contents("pages/paystack-webhook-headers.html", "<pre>" . json_encode($headers) . "</pre>", FILE_APPEND);

    define('PAYSTACK_SECRET_KEY', $config['paystack_secret_key']);

    if (!isset($_SERVER['HTTP_X_PAYSTACK_SIGNATURE']) ||
        $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] !== hash_hmac('sha512', $paymentDetails, PAYSTACK_SECRET_KEY)) {
        http_response_code(403); 
        echo json_encode(['error' => 'Invalid signature']);
        exit();
    }

    http_response_code(200);

    // Decode the event payload
    $event = json_decode($paymentDetails);
    $chargeEvent = $event->event;
    $reference = $event->data->reference;
    $amount = $event->data->amount / 100;
    $status = $event->data->status;
    $first_name = $event->data->customer->first_name;
    $last_name = $event->data->customer->last_name;
    $customer_email = $event->data->customer->email;
    $customer_code = $event->data->customer->customer_code;
    $txid = $event->data->id;
    $gateway_response = $event->data->gateway_response;
    $channel = $event->data->channel;

    // Accessing metadata values
    $metadata = $event->data->metadata ?? new stdClass();
    $routername = $metadata->router ?? '';
    $planid = $metadata->planid ?? '';
    $userid = $metadata->userid ?? '';
    $amountToPay = $metadata->price ?? '';

    // Handle missing reference
    if (!$reference) {
        sendTelegram(Lang::T("No reference supplied from webhook"));
        exit();
    }

    // Fetch the transaction
    $trx = ORM::for_table('tbl_payment_gateway')
        ->where('gateway_trx_id', $reference)
        ->find_one();

    if (!$trx) {
        _log("Transaction with reference $reference not found.");
        http_response_code(404); 
        exit();
    }

    if (in_array($trx->status, ['2', '3', '4'])) {
        exit();
    }

    sleep(10);

    if ($status === 'success' && $amount >= $amountToPay) {
        if (!Package::rechargeUser($userid, $routername, $planid, 'PAYSTACK', $channel)) {
            _log('[' . Lang::T("Failed to activate their package, try again later.") . ']: Paystack Payment Webhook Reports: ' . " \n Payment Status: " . $status . " \n Payment Confirmation: " . $gateway_response . " \n API Response:\n" . json_encode($event));
            sendTelegram('[' . Lang::T("Failed to activate their package, try again later.") . ']: Paystack Payment Webhook Reports: ' . "\n Payment Status: " . $status . " \n Payment Confirmation: " . $gateway_response . " \n API Response:\n" . json_encode($event));
        } else {
            paystack_payment_notificationupdateTransaction($trx, 2, $event, $channel, $gateway_response);
            sendTelegram('[' . Lang::T("Success") . ']: Paystack Payment Webhook Reports: ' . " \n Payment Status: " . $status . " \n Payment Confirmation: " . $gateway_response . " \n API Response:\n" . json_encode($event));
            _log('[' . Lang::T("Notification") . ']: Paystack Payment Webhook Reports: ' . " \n Payment Status: " . $status . " \n Payment Confirmation: " . $gateway_response . " \n API Response:\n" . json_encode($event));
        }
    }
    // Handle failed payment
    elseif ($status === 'failed') {
        paystack_payment_notificationupdateTransaction($trx, 4, $event, $channel, $gateway_response);
        _log('[' . Lang::T("Notification") . ']: Paystack Payment Webhook Reports: ' . " \n Payment Status: " . $status . " \n Payment Confirmation: " . $gateway_response . " \n API Response:\n" . json_encode($event));
        sendTelegram("Paystack Payment Status: " . $status . "\n\n" . json_encode($event, JSON_PRETTY_PRINT));
    }
    elseif ($status === 'abandoned') {
        paystack_payment_notificationupdateTransaction($trx, 4, $event, $channel, $gateway_response);
        _log('[' . Lang::T("Notification") . ']: Paystack Payment Webhook Reports: ' . " \n Payment Status: " . $status . " \n Payment Confirmation: " . $gateway_response . " \n API Response:\n" . json_encode($event));
        sendTelegram("Paystack Payment Status: " . $status . "\n\n" . json_encode($event, JSON_PRETTY_PRINT));
    }
    else {
        _log('[' . Lang::T("Notification") . ']: Paystack Payment Webhook Reports: ' . " \n Payment Status: " . $status . " \n Payment Confirmation: " . $gateway_response . " \n API Response:\n" . json_encode($event));
        sendTelegram("Paystack Webhook: Unknown result\n\n" . json_encode($event, JSON_PRETTY_PRINT));
    }

    function paystack_payment_notificationupdateTransaction($trx, $status, $event, $channel, $gateway_response)
    {
        $trx->pg_paid_response = json_encode($event);
        $trx->payment_method = 'Paystack';
        $trx->payment_channel = $channel;
        $trx->paid_date = date('Y-m-d H:i:s', strtotime($event->data->created_at));
        $trx->status = $status; 
        $trx->save();
    }
}


function paystack_get_status($trx, $user)
{
    global $config;

    $response = Http::getData('https://api.paystack.co/transaction/verify/' . $trx['gateway_trx_id'], [
        'Authorization: Bearer ' . $config['paystack_secret_key'],
        'Cache-Control: no-cache'
    ]);
    
    $result = json_decode($response, true);

    if (!$result || !isset($result['data'])) {
        r2(U . "order/view/" . $trx['id'], 'd', Lang::T("Invalid response from Paystack."));
        return;
    }

    $data = $result['data'];
    $amountPaid = $data['amount'] / 100; 
    $amountToPay = $data['requested_amount'];
    $status = $data['status'];
    $gatewayResponse = $data['gateway_response'];

    if ($status === 'success' && $amountPaid >= $amountToPay &&
        ($gatewayResponse === 'Successful' || $gatewayResponse === 'Approved' || $gatewayResponse === '[Test] Approved')) {

        if (!Package::rechargeUser($user['id'], $trx['routers'], $trx['plan_id'], $trx['gateway'], $data['channel'])) {
            r2(U . "order/view/" . $trx['id'], 'd', Lang::T("Failed to activate your Package, try again later."));
        }

        $trx->pg_paid_response = json_encode($result);
        $trx->payment_method = 'Paystack';
        $trx->payment_channel = $data['channel'];
        $trx->paid_date = date('Y-m-d H:i:s', strtotime($data['created_at']));
        $trx->status = 2; 
        $trx->save();

        r2(U . "order/view/" . $trx['id'], 's', Lang::T("Transaction Completed Successfully."));
    }

    else if ($status === 'pending') {
        $trx->pg_paid_response = json_encode($result);
        $trx->status = 1; // Pending
        $trx->save();

        r2(U . "order/view/" . $trx['id'], 'd', Lang::T("Transaction Pending. Please wait for some minutes."));
    }

    else if ($status === 'failed' || $status === 'canceled') {
        $trx->pg_paid_response = json_encode($result);
        $trx->status = 3; // Failed
        $trx->save();

        r2(U . "order/view/" . $trx['id'], 'd', Lang::T("Transaction Canceled or Failed."));
    }

    else if ($trx->status == 2) {
        r2(U . "order/view/" . $trx['id'], 'd', Lang::T("Transaction has already been paid."));
    }

    else {
        Message::sendTelegram("paystack_get_status: unknown result\n\n" . json_encode($result, JSON_PRETTY_PRINT));
        r2(U . "order/view/" . $trx['id'], 'd', Lang::T("Unknown Command."));
    }
}
