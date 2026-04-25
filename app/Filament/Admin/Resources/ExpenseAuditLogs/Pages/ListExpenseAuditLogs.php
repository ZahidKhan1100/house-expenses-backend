<?php

namespace App\Filament\Admin\Resources\ExpenseAuditLogs\Pages;

use App\Filament\Admin\Resources\ExpenseAuditLogs\ExpenseAuditLogResource;
use Filament\Resources\Pages\ListRecords;

class ListExpenseAuditLogs extends ListRecords
{
    protected static string $resource = ExpenseAuditLogResource::class;
}
