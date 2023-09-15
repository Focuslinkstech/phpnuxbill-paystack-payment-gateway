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
     global $ui;
     $ui->assign('_title', 'Paystack - Payment Gateway');
     $ui->assign('cur', json_decode(file_get_contents('system/paymentgateway/paystack_currency.json'), true));
     $ui->assign('channel', json_decode(file_get_contents('system/paymentgateway/paystack_channel.json'), true));
     $ui->display('paystack.tpl');
 }


 function paystack_save_config()
 {
     global $admin, $_L;
     $paystack_secret_key = _post('paystack_secret_key');
     $paystack_currency = _post('paystack_currency');
     $d = ORM::for_table('tbl_appconfig')->where('setting', 'paystack_secret_key')->find_one();
     if ($d) {
         $d->value = $paystack_secret_key;
         $d->save();
     } else {
         $d = ORM::for_table('tbl_appconfig')->create();
         $d->setting = 'paystack_secret_key';
         $d->value = $paystack_secret_key;
         $d->save();
     }
     $d = ORM::for_table('tbl_appconfig')->where('setting', 'paystack_currency')->find_one();
     if ($d) {
         $d->value = $paystack_currency;
         $d->save();
     } else {
         $d = ORM::for_table('tbl_appconfig')->create();
         $d->setting = 'paystack_currency';
         $d->value = $paystack_currency;
         $d->save();
     }
     $d = ORM::for_table('tbl_appconfig')->where('setting', 'paystack_channel')->find_one();
    if ($d) {
        $d->value = implode(',', $_POST['paystack_channel']);
        $d->save();
    } else {
        $d = ORM::for_table('tbl_appconfig')->create();
        $d->setting = 'paystack_channel';
        $d->value = implode(',', $_POST['paystack_channel']);
        $d->save();
    }
     _log('[' . $admin['username'] . ']: paystack ' . $_L['Settings_Saved_Successfully'], 'Admin', $admin['id']);

     r2(U . 'paymentgateway/paystack', 's', $_L['Settings_Saved_Successfully']);
 }

function paystack_create_transaction($trx, $user)
{
  global $config;
  $txref = uniqid('trx');
  $total = $trx['price']*100;
    $json = [
       'reference' => $txref,
       'amount' => $total,
       'currency' => $config['paystack_currency'],
       'channels' => explode(',', $config['paystack_channel']),
       'email' => (empty($user['email'])) ? $user['username'] . '@' . $_SERVER['HTTP_HOST'] : $user['email'],
       'customer' => [
           'firstname' =>  $user['fullname'],
           'phone' => $user['phonenumber']
       ],
       'meta' => [
           'price' => $trx['price']
       ],

       'customizations' => [
           'title' => $trx['plan_name'],
           'description' => $trx['plan_name']
       ],

       'callback_url' => U . 'order/view/' . $trx['id'] . '/check'
   ];
//  die(json_encode($json,JSON_PRETTY_PRINT));

 $result = json_decode(Http::postJsonData(paystack_get_server() . 'initialize', $json,[
              'Authorization: Bearer ' . $config['paystack_secret_key'],
              'Cache-Control: no-cahe'
            ],
        ),
true);

//die(json_encode($result,JSON_PRETTY_PRINT));

if ($result['status'] == false) {
        Message::sendTelegram("Paystack payment failed\n\n" . json_encode($result, JSON_PRETTY_PRINT));
        r2(U . 'order/package', 'e', Lang::T("Failed to create transaction.\n".$result['message']));
    }
    $d = ORM::for_table('tbl_payment_gateway')
        ->where('username', $user['username'])
        ->where('status', 1)
        ->find_one();
    $d->gateway_trx_id = $result['data']['reference'];
    $d->pg_url_payment = $result['data']['authorization_url'];
    $d->pg_request = json_encode($result);
    $d->expired_date = date('Y-m-d H:i:s', strtotime("+ 6 HOUR"));
    $d->save();

    header('Location: ' . $result['data']['authorization_url']);
  exit();

    //r2(U . "order/view/" . $d['id'], 's', Lang::T("Create Transaction Success"));


}

function paystack_payment_notification()
{
  //to be implemented
 }

 function paystack_get_status($trx, $user)
 {
     global $config;
     $result = json_decode(Http::getData(paystack_get_server() . 'verify/' . $trx['gateway_trx_id'], [
       'Authorization: Bearer ' . $config['paystack_secret_key'],
       'Cache-Control: no-cahe'
     ]), true);
     $amountPaid = $result['data']['amount'];
     $amountToPay = $result['data']['requested_amount'];
   if ($result['status'] == true && $result['data']['status'] === 'abandoned' || $result['data']['status'] ==='failed' || $amountPaid < $amountToPay ){
      // die(json_encode($result,JSON_PRETTY_PRINT));
       r2(U . "order/view/" . $trx['id'], 'w', Lang::T("Transaction still unpaid."));
   }else if (in_array($result['status'] == true && $result['data']['status'], ['success']) && $trx['status'] != 2) {
       if (!Package::rechargeUser($user['id'], $trx['routers'], $trx['plan_id'], $trx['gateway'], $result['data']['channel'])) {
           r2(U . "order/view/" . $trx['id'], 'd', Lang::T("Failed to activate your Package, try again later."));
  }
  $trx->pg_paid_response = json_encode($result);
  $trx->payment_method = 'Paystack';
  $trx->payment_channel = $result['data']['channel'];
  $trx->paid_date = date('Y-m-d H:i:s', strtotime( $result['data']['created_at']));
  $trx->status = 2;
  $trx->save();

  r2(U . "order/view/" . $trx['id'], 's', Lang::T("Transaction successful."));
} else if ($result['status'] == 'EXPIRED') {
  $trx->pg_paid_response = json_encode($result);
  $trx->status = 3;
  $trx->save();
  r2(U . "order/view/" . $trx['id'], 'd', Lang::T("Transaction expired."));
} else if ($trx['status'] == 2) {
  r2(U . "order/view/" . $trx['id'], 'd', Lang::T("Transaction has been paid.."));
}else{
  Message::sendTelegram("paystack_get_status: unknown result\n\n".json_encode($result, JSON_PRETTY_PRINT));
  r2(U . "order/view/" . $trx['id'], 'd', Lang::T("Unknown Command."));
}

}


function paystack_get_server()
{
    global $_app_stage;
    if ($_app_stage == 'Live') {
        return 'https://api.paystack.co/transaction/';
    } else {
        return 'https://api.paystack.co/transaction/';
    }
}
