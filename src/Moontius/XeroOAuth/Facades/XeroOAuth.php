<?php 

namespace Moontius\XeroOAuth\Facades;

use Illuminate\Support\Facades\Facade;

class XeroOAuth extends Facade
{
	/**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor() { return 'xero-oauth'; }
}