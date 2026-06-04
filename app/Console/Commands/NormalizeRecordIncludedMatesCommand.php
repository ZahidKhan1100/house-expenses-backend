<?php

namespace App\Console\Commands;

use App\Models\Record;
use App\Services\ExpenseSplit;
use Illuminate\Console\Command;

class NormalizeRecordIncludedMatesCommand extends Command
{
    protected $signature = 'split:normalize-included
        {--house= : Only records for this house (via expense)}
        {--dry-run : Show changes without saving}';

    protected $description = 'Sort included_mates by user id on all records (stable cent remainders)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $houseId = $this->option('house') ? (int) $this->option('house') : null;

        $query = Record::query()->with('expense');
        if ($houseId !== null) {
            $query->whereHas('expense', fn ($q) => $q->where('house_id', $houseId));
        }

        $updated = 0;
        foreach ($query->cursor() as $record) {
            $raw = is_array($record->included_mates) ? $record->included_mates : [];
            $sorted = ExpenseSplit::sortIncludedMates($raw);
            if ($sorted === $raw) {
                continue;
            }

            $this->line(sprintf('Record #%d: reorder included %s → %s',
                $record->id,
                json_encode(array_column($raw, 'id')),
                json_encode(array_column($sorted, 'id')),
            ));

            if (! $dryRun) {
                $record->included_mates = $sorted;
                $record->save();
            }
            $updated++;
        }

        $this->info(($dryRun ? 'Would update ' : 'Updated ').$updated.' record(s).');

        if ($updated > 0 && ! $dryRun) {
            $this->comment('Run: php artisan cache:clear (or railway ssh -- php artisan cache:clear)');
        }

        return self::SUCCESS;
    }
}
