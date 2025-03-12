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
                            <input type="password" class="form-control" id="paystack_secret_key" name="paystack_secret_key"
                                value="{$_c['paystack_secret_key']}">
                            <a href="https://dashboard.paystack.co/#/settings/developer" target="_blank"
                                class="help-block">https://dashboard.paystack.co/#/settings/developer</a>
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
                            <button class="btn btn-primary waves-effect waves-light" type="submit">Save</button>
                        </div>
                    </div>
                    <div class="bs-callout bs-callout-info" id="callout-navbar-role">
                        <h4>Paystack Settings in Mikrotik</h4>
                        /ip hotspot walled-garden <br>
                        add dst-host=paystack.com <br>
                        add dst-host=*.paystack.com <br><br>
                        <small class="form-text text-muted">Set Telegram Bot to get any error and
                            notification</small>

                    </div>
                </div>
            </div>

        </div>
    </div>
</form>

{include file="sections/footer.tpl"}