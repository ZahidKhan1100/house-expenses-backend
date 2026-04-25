<?php

namespace App\Filament\Admin\Resources\ExpenseAuditLogs;

use App\Filament\Admin\Resources\ExpenseAuditLogs\Pages\ListExpenseAuditLogs;
use App\Filament\Admin\Resources\ExpenseAuditLogs\Schemas\ExpenseAuditLogForm;
use App\Filament\Admin\Resources\ExpenseAuditLogs\Tables\ExpenseAuditLogsTable;
use App\Models\ExpenseAuditLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ExpenseAuditLogResource extends Resource
{
    protected static ?string $model = ExpenseAuditLog::class;

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Audit log';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    public static function form(Schema $schema): Schema
    {
        return ExpenseAuditLogForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ExpenseAuditLogsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExpenseAuditLogs::route('/'),
        ];
    }
}
