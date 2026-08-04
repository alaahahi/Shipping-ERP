<?php

namespace App\Enums;

enum WhatsappNotificationType: string
{
    case VoyageLoaded = 'voyage_loaded';
    case VoyageRevenuePosted = 'voyage_revenue_posted';
    case PaymentReceived = 'payment_received';
    case VoyageDeparted = 'voyage_departed';
    case VoyageArrived = 'voyage_arrived';

    public function label(): string
    {
        return match ($this) {
            self::VoyageLoaded => 'Cars loaded on voyage',
            self::VoyageRevenuePosted => 'Voyage revenue posted',
            self::PaymentReceived => 'Payment received',
            self::VoyageDeparted => 'Voyage departed',
            self::VoyageArrived => 'Voyage arrived',
        };
    }

    public function defaultTemplate(): string
    {
        return match ($this) {
            self::VoyageLoaded => "Hello {{company_name}}, your cars have been loaded on voyage {{voyage_number}}. Ship: {{ship_name}}.",
            self::VoyageRevenuePosted => "Hello {{company_name}}, the revenue for voyage {{voyage_number}} has been posted. Total due: {{amount}} {{currency}}.",
            self::PaymentReceived => "Hello {{company_name}}, we received a payment of {{amount}} {{currency}}. Thank you.",
            self::VoyageDeparted => "Hello {{company_name}}, voyage {{voyage_number}} on ship {{ship_name}} has departed from {{pol}}.",
            self::VoyageArrived => "Hello {{company_name}}, voyage {{voyage_number}} on ship {{ship_name}} has arrived at {{pod}}.",
        };
    }
}
