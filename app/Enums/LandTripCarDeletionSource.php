<?php

namespace App\Enums;

enum LandTripCarDeletionSource: string
{
    case Manual = 'manual';
    case ImportUndo = 'import_undo';
    case Backfill = 'backfill';
}
