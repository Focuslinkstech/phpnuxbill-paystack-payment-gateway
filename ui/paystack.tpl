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

                    {assign var=paystack_channel value=$_c.paystack_channel|default:[]}
                    <div class="form-group">
                        <label class="col-md-2 control-label">Payment Channels</label>
                        <div class="col-md-6">
                            <label class="checkbox-inline">
                                <input type="checkbox" {if in_array('card', $_c.paystack_channel)}checked="true"{/if} id="paystack_channel_card" name="paystack_channel[]" value="card">
                                Card Payment
                            </label>
                            <label class="checkbox-inline">
                                <input type="checkbox" {if in_array('ussd', $_c.paystack_channel)}checked="true"{/if} id="paystack_channel_ussd" name="paystack_channel[]" value="ussd">
                                USSD
                            </label>
                            <label class="checkbox-inline">
                                <input type="checkbox" {if in_array('bank', $_c.paystack_channel)}checked="true"{/if} id="paystack_channel_bank" name="paystack_channel[]" value="bank">
                                Bank
                            </label>
                            <label class="checkbox-inline">
                                <input type="checkbox" {if in_array('bank_transfer', $_c.paystack_channel)}checked="true"{/if} id="paystack_channel_bank_transfer" name="paystack_channel[]" value="bank_transfer">
                                Bank Transfer
                            </label>
                            <label class="checkbox-inline">
                                <input type="checkbox" {if in_array('qr', $_c.paystack_channel)}checked="true"{/if} id="paystack_channel_qr" name="paystack_channel[]" value="qr">
                                QR
                            </label>
                            <label class="checkbox-inline">
                                <input type="checkbox" {if in_array('mobile_money', $_c.paystack_channel)}checked="true"{/if} id="paystack_channel_mobile_money" name="paystack_channel[]" value="mobile_money">
                                Mobile Money
                            </label>
                        </div>
                    </div>                    
                    <div class="form-group">
                        <label class="col-md-2 control-label">Currency</label>
                        <div class="col-md-6">
                            <select class="form-control" name="paystack_currency">
                                <option value="NGN" {if $_c['paystack_currency']==='NGN' } selected{/if}>
                                    Nigerian Naira</option>
                                <option value="GHC" {if $_c['paystack_currency']==='GHC' } selected{/if}>Ghana
                                    Cedis</option>
                                <option value="KES" {if $_c['paystack_currency']==='KES' } selected{/if}>Kenyan
                                    Shilling</option>
                                <option value="ZAR" {if $_c['paystack_currency']==='ZAR' } selected{/if}>South
                                    African Rand</option>
                                <option value="USD" {if $_c['paystack_currency']==='USD' } selected{/if}>United
                                    States Dollar</option>

                            </select>
                            <small class="form-text text-muted">Attention</small>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-2 control-label">Webhook Url</label>
                        <div class="col-md-6">
                            <input type="text" readonly class="form-control" onclick="this.select()"
                                value="{$_url}callback/paystack">
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="col-lg-offset-2 col-lg-10">
                            <button class="btn btn-primary waves-effect waves-light"
                                type="submit">Save</button>
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
