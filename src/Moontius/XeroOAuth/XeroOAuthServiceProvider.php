<?php

namespace Moontius\XeroOAuth;

use Illuminate\Support\ServiceProvider;
use Moontius\XeroOAuth\OAuthHelper;
class XeroOAuthServiceProvider extends ServiceProvider {

    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register() {
        $this->app->singleton('xero-oauth', function ($app) {
            $config = $app['config']['xero']['oauth'];
            return new OAuthHelper($config);
        });


        $this->app->bind(OAuthHelper::class, function ($app) {
            return $app['xero-oauth'];
        });
    }

    public function boot() {
        $this->publishes([
            __DIR__ . '/config.php' => config_path('xero.php'),
        ]);
    }

}
