<?php

namespace App\Enums;

enum GoogleBusinessProfileConnectionStatus: string
{
    case NotConnected = 'not_connected';
    case PendingDiscovery = 'pending_discovery';
    case NeedsConfirmation = 'needs_confirmation';
    case Connected = 'connected';
    case NoMatch = 'no_match';
    case ReconnectRequired = 'reconnect_required';
    case Disconnected = 'disconnected';
}
