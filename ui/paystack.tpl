{include file="sections/header.tpl"}

<form class="form-horizontal" method="post" role="form" action="{$_url}paymentgateway/paystack">
    <div class="row">
        <div class="col-sm-12 col-md-12">
            <div class="panel panel-primary panel-hovered panel-stacked mb30">
                <div class="panel-heading">Paystack Payment Gateway</div>
                <div class="panel-body">
                    <div class="form-group">
                        <label class="col-md-2 control-label">Paystack Secret Key</label>
                        <div class="col-md-6">
                            <input type="text" class="form-control" id="paystack_secret_key" name="paystack_secret_key"
                                value="{$_c['paystack_secret_key']}">
                            <a href="https://dashboard.paystack.co/#/settings/developer" target="_blank"
                                class="help-block">https://dashboard.paystack.co/#/settings/developer</a>
                        </div>
                    </div>

					 <div class="form-group">
                        <label class="col-md-2 control-label">Payment Channels</label>
                        <div class="col-md-6">
                            {foreach $channel as $payment_options}
                                <label class="checkbox-inline"><input type="checkbox" {if strpos($_c['paystack_channel'], $payment_options['id']) !== false}checked="true"{/if} id="paystack_channel" name="paystack_channel[]" value="{$payment_options['id']}"> {$payment_options['name']}</label>
                            {/foreach}
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-2 control-label">Currency</label>
                        <div class="col-md-6">
                            <select class="form-control" name="paystack_currency">
                                {foreach $cur as $currency}
                                    <option value="{$currency['id']}"
                                    {if $currency['id'] == $_c['paystack_currency']}selected{/if}
                                    >{$currency['id']} - {$currency['name']}</option>
                                {/foreach}
                            </select>
                            <small class="form-text text-muted">Attention</small>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="col-lg-offset-2 col-lg-10">
                            <button class="btn btn-primary waves-effect waves-light"
                                type="submit">{$_L['Save']}</button>
                        </div>
                    </div>
                    <pre>/ip hotspot walled-garden
add dst-host=paystack.com
add dst-host=*.paystack.com</pre>
                    <small class="form-text text-muted">Set Telegram Bot to get any error and
                        notification</small>
                </div>
            </div>

        </div>
    </div>
</form>

{include file="sections/footer.tpl"}
