<?php

namespace QD\commerce\shipmondo\plugin;

use craft\events\RegisterUrlRulesEvent;
use craft\web\UrlManager;
use yii\base\Event;

trait Routes
{

    private function _registerSiteRoutes(): void
    {
        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_SITE_URL_RULES, function (RegisterUrlRulesEvent $event) {
            //Services
            $event->rules['shipmondo/services/list-service-points'] = 'commerce-shipmondo/services/list-service-points';
            $event->rules['shipmondo/services/list-service-points-by-params'] = 'commerce-shipmondo/services/list-service-points-by-params';

            //Webhooks
            $event->rules['shipmondo/webhook/order/status'] = 'commerce-shipmondo/webhooks/order-update-status';
        });
    }

    private function _registerCpRoutes(): void
    {
        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES, function (RegisterUrlRulesEvent $event) {
            //Plugin settings
            $event->rules['shipmondo/settings'] = 'commerce-shipmondo/settings/index';
            $event->rules['commerce-shipmondo'] = 'commerce-shipmondo/settings/index';
            $event->rules['commerce-shipmondo/settings'] = 'commerce-shipmondo/settings/index';
        });
    }
}
