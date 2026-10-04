<?php

namespace App\Enums;

enum InventoryAdjustmentReason: string
{
    case StockReceived = 'stock_received';
    case Correction = 'correction';
    case DamagedOrLost = 'damaged_or_lost';
    case CustomerReturn = 'customer_return';
    case Other = 'other';
    case CsvImport = 'csv_import';
}
