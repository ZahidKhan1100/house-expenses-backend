<?php

namespace App\Console\Commands;

use App\Models\Expense;
use App\Models\House;
use App\Models\Record;
use App\Models\User;
use App\Services\BalanceCalculator;
use App\Services\ExpenseSplit;
use App\Services\SettlementEngine;
use App\Services\SettlementService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class AuditSplitMonthCommand extends Command
{
    protected $signature = 'split:audit
        {month : YYYY-MM e.g. 2026-05}
        {--users= : Comma-separated user ids (default 3,4,5,6,7,8)}
        {--house= : House id (auto-detect from users if omitted)}
        {--from-api : Read month from production API (HABIMATE_API_TOKEN + HABIMATE_API_URL)}
        {--api-token= : Bearer token (overrides HABIMATE_API_TOKEN)}
        {--api-url= : API base, default https://api.habimate.com/api/v1}';

    protected $description = 'Audit cent splits for a month (DB or production API via --from-api)';

    public function handle(
        BalanceCalculator $calculator,
        SettlementService $settlementService,
        SettlementEngine $engine,
    ): int {
        $month = (string) $this->argument('month');
        $userIds = array_map('intval', array_filter(explode(',', (string) ($this->option('users') ?: '3,4,5,6,7,8'))));

        $house = null;
        $houseId = null;
        $records = collect();
        $apiSettlements = null;

        if ($this->option('from-api')) {
            $payload = $this->loadFromProductionApi($month);
            if ($payload === null) {
                return self::FAILURE;
            }
            $houseId = (int) $payload['house_id'];
            $house = (object) [
                'id' => $houseId,
                'name' => $payload['house_name'],
                'guest_day_weight_percent' => $payload['guest_day_weight_percent'],
            ];
            $records = $payload['records'];
            $apiSettlements = $payload['settlements'];
            $this->info('Source: production API ('.rtrim($payload['api_url'], '/').')');
        } else {
            $houseId = $this->option('house') ? (int) $this->option('house') : $this->detectHouseId($userIds);
            if ($houseId === null) {
                $this->error('Could not detect house_id for users: '.implode(',', $userIds));

                return self::FAILURE;
            }

            $house = House::query()->find($houseId);
            $expense = Expense::query()->where('house_id', $houseId)->where('month', $month)->first();

            if (! $expense) {
                $this->warn("No expense row for house {$houseId} month {$month}");

                return self::FAILURE;
            }

            $records = Record::query()
                ->where('expense_id', $expense->id)
                ->with('category')
                ->orderBy('id')
                ->get();

            $this->info('Source: database (local/Railway DB connection)');
        }

        $mateIds = $records
            ->pluck('paid_by')
            ->merge($records->flatMap(fn ($r) => collect($r->included_mates ?? [])->pluck('id')))
            ->unique()
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $houseName = is_object($house) ? ($house->name ?? '?') : ($house?->name ?? '?');
        $this->info("House #{$houseId} ({$houseName}) — month {$month} — records: {$records->count()}");
        $this->line('Mate ids in month: '.implode(', ', $mateIds));
        $this->newLine();

        $monthOrphanTotal = 0;
        $issues = [];

        foreach ($records as $rec) {
            $included = array_values(array_filter(
                is_array($rec->included_mates) ? $rec->included_mates : [],
                fn ($m) => in_array((int) ($m['id'] ?? 0), $mateIds, true),
            ));
            $nHouse = count($mateIds);
            $nIncluded = count($included);
            $total = (float) $rec->amount;
            $totalCents = (int) round($total * 100);

            if ($nIncluded === 0) {
                continue;
            }

            if (($rec->split_method ?? 'equal') === 'days') {
                $split = ExpenseSplit::sharePerUserWeightedFloored($total, $this->weightedMates($rec, $included));
            } else {
                $split = ExpenseSplit::sharePerUserFloored($total, $included);
            }

            $shares = $split['shares'];
            $orphan = (int) ($split['orphan_cents'] ?? 0);
            $monthOrphanTotal += $orphan;

            $allocatedCents = 0;
            foreach ($included as $m) {
                $id = (int) $m['id'];
                $allocatedCents += (int) round(((float) ($shares[$id] ?? 0)) * 100);
            }

            $gap = $totalCents - $allocatedCents;
            $partial = $nIncluded < $nHouse;
            $cat = is_object($rec->category ?? null)
                ? ($rec->category->name ?? '—')
                : (is_array($rec->category ?? null) ? ($rec->category['name'] ?? '—') : '—');

            $this->line(sprintf(
                'Record #%d | %s | $%s | payer %d | included %d/%d | orphan %d¢ | allocated %d¢ | gap %d¢%s',
                $rec->id,
                $cat,
                number_format($total, 2),
                (int) $rec->paid_by,
                $nIncluded,
                $nHouse,
                $orphan,
                $allocatedCents,
                $gap,
                $partial ? ' [PARTIAL]' : '',
            ));

            if ($orphan !== $gap) {
                $issues[] = "Record #{$rec->id}: orphan_cents ({$orphan}) != gap ({$gap})";
            }
            if ($orphan > 0 && $partial) {
                $this->line('  included ids: '.implode(',', array_map(fn ($m) => (int) $m['id'], $included)));
            }
        }

        $this->newLine();
        $this->info("Month orphan cents (pre-distribution): {$monthOrphanTotal}");

        $gwp = (float) (is_object($house) ? ($house->guest_day_weight_percent ?? 100) : ($house?->guest_day_weight_percent ?? 100));
        $balances = $calculator->calculate($records, $mateIds, $gwp);
        $balancesAfterPaid = $this->option('from-api')
            ? $this->applyApiPaidSettlements($balances, $apiSettlements ?? [])
            : $settlementService->applyPaidSettlementsToNetBalances($houseId, $month, $balances);
        $tx = $engine->optimize($balancesAfterPaid);

        if ($this->option('from-api') && ($apiSettlements ?? []) !== []) {
            $this->newLine();
            $this->info('Settlements stored on server (API):');
            foreach ($apiSettlements as $s) {
                $this->line(sprintf(
                    '  #%s %d→%d $%s [%s]',
                    $s['id'] ?? '?',
                    (int) $s['from_user_id'],
                    (int) $s['to_user_id'],
                    number_format((float) $s['amount'], 2),
                    $s['status'] ?? '?',
                ));
            }
        }

        $this->newLine();
        $this->info('Net balances (expenses only):');
        foreach ($userIds as $uid) {
            $name = User::withTrashed()->find($uid)?->name ?? '?';
            $b = $balances[$uid] ?? 0.0;
            $this->line(sprintf('  user %d (%s): $%s', $uid, $name, number_format($b, 2)));
        }

        $sumBalances = round(array_sum($balances), 2);
        $this->line("  SUM: {$sumBalances}".($sumBalances !== 0.0 ? ' ⚠️ should be 0' : ' ✓'));

        $this->newLine();
        $this->info('After paid settlements:');
        foreach ($userIds as $uid) {
            $b = $balancesAfterPaid[$uid] ?? 0.0;
            $this->line(sprintf('  user %d: $%s', $uid, number_format($b, 2)));
        }
        $sumAfter = round(array_sum($balancesAfterPaid), 2);
        $this->line("  SUM: {$sumAfter}".($sumAfter !== 0.0 ? ' ⚠️ should be 0' : ' ✓'));

        $this->newLine();
        $this->info('Settlement transactions:');
        $txSum = 0.0;
        foreach ($tx as $row) {
            $this->line(sprintf(
                '  %d → %d: $%s',
                $row['from_user_id'],
                $row['to_user_id'],
                number_format((float) $row['amount'], 2),
            ));
            $txSum += (float) $row['amount'];
        }
        if ($tx === []) {
            $this->line('  (none)');
        }

        $creditorSum = 0;
        $debtorSum = 0;
        foreach ($balancesAfterPaid as $v) {
            if ($v > 0) {
                $creditorSum += $v;
            }
            if ($v < 0) {
                $debtorSum += abs($v);
            }
        }
        $this->newLine();
        $this->line(sprintf('Creditors owed total: $%s | Debtors owe total: $%s | diff: $%s',
            number_format($creditorSum, 2),
            number_format($debtorSum, 2),
            number_format($creditorSum - $debtorSum, 2),
        ));

        if ($monthOrphanTotal > 0) {
            $debtorCount = count(array_filter($balances, fn ($v) => $v < -0.001));
            if ($debtorCount === 0) {
                $issues[] = "Orphan cents {$monthOrphanTotal} but no net debtors before paid settlements — orphans not applied";
            }
        }

        if ($issues !== []) {
            $this->newLine();
            $this->error('Issues:');
            foreach ($issues as $i) {
                $this->line('  - '.$i);
            }
        } else {
            $this->newLine();
            $this->info('No structural issues detected in this audit.');
        }

        return self::SUCCESS;
    }

    /**
     * @return array{house_id:int,house_name:string,guest_day_weight_percent:float,records:\Illuminate\Support\Collection, settlements:list<array>, api_url:string}|null
     */
    private function loadFromProductionApi(string $month): ?array
    {
        $token = (string) ($this->option('api-token') ?: env('HABIMATE_API_TOKEN', ''));
        $base = rtrim((string) ($this->option('api-url') ?: env('HABIMATE_API_URL', 'https://api.habimate.com/api/v1')), '/');

        if ($token === '') {
            $this->error('Missing API token. Set HABIMATE_API_TOKEN or pass --api-token=');

            return null;
        }

        $headers = ['Accept' => 'application/json', 'Authorization' => 'Bearer '.$token];
        $expResp = Http::withHeaders($headers)->get("{$base}/expenses");
        if (! $expResp->successful()) {
            $this->error('GET /expenses failed: '.$expResp->status().' '.$expResp->body());

            return null;
        }

        $monthExpense = null;
        foreach ($expResp->json() as $row) {
            if (($row['month'] ?? null) === $month) {
                $monthExpense = $row;
                break;
            }
        }

        if ($monthExpense === null) {
            $this->error("No expense month {$month} for this API user/house.");

            return null;
        }

        $profile = Http::withHeaders($headers)->get("{$base}/profile");
        $houseName = 'Unknown';
        $houseId = 0;
        $gwp = 100.0;
        if ($profile->successful()) {
            $house = $profile->json('house') ?? [];
            $houseName = (string) ($house['name'] ?? 'Unknown');
            $houseId = (int) ($house['id'] ?? 0);
            $gwp = (float) ($house['guest_day_weight_percent'] ?? 100);
        }

        $records = collect($monthExpense['records'] ?? [])->map(function (array $row) {
            $rec = new Record;
            $rec->forceFill([
                'id' => (int) ($row['id'] ?? 0),
                'amount' => (float) ($row['amount'] ?? 0),
                'paid_by' => (int) ($row['paid_by'] ?? 0),
                'included_mates' => $row['included_mates'] ?? [],
                'split_method' => $row['split_method'] ?? 'equal',
                'bill_period_days' => (int) ($row['bill_period_days'] ?? 0),
            ]);
            if (! empty($row['category'])) {
                $rec->setRelation('category', (object) ['name' => $row['category']['name'] ?? '—']);
            }

            return $rec;
        })->sortBy('id')->values();

        $settleResp = Http::withHeaders($headers)->get("{$base}/settlements", ['month' => $month]);
        $settlements = $settleResp->successful() ? ($settleResp->json('settlements') ?? []) : [];

        return [
            'house_id' => $houseId,
            'house_name' => $houseName,
            'guest_day_weight_percent' => $gwp,
            'records' => $records,
            'settlements' => $settlements,
            'api_url' => $base,
        ];
    }

    /** @param  list<array<string, mixed>>  $settlements */
    private function applyApiPaidSettlements(array $balance, array $settlements): array
    {
        $balanceCents = [];
        foreach ($balance as $id => $v) {
            $balanceCents[(int) $id] = (int) round(((float) $v) * 100);
        }

        foreach ($settlements as $s) {
            if (($s['status'] ?? '') !== 'paid') {
                continue;
            }
            if (($s['type'] ?? 'expense') !== 'expense' && ($s['type'] ?? null) !== null) {
                continue;
            }
            $from = (int) ($s['from_user_id'] ?? 0);
            $to = (int) ($s['to_user_id'] ?? 0);
            $amtCents = (int) round(((float) ($s['amount'] ?? 0)) * 100);
            $balanceCents[$from] = ($balanceCents[$from] ?? 0) + $amtCents;
            $balanceCents[$to] = ($balanceCents[$to] ?? 0) - $amtCents;
        }

        $out = [];
        foreach ($balanceCents as $id => $cents) {
            $dollars = $cents / 100.0;
            $out[$id] = abs($dollars) < 0.005 ? 0.0 : round($dollars, 2);
        }

        return $out;
    }

    /** @param  list<int>  $userIds */
    private function detectHouseId(array $userIds): ?int
    {
        $row = User::query()->whereIn('id', $userIds)->whereNotNull('house_id')->first();

        return $row?->house_id ? (int) $row->house_id : null;
    }

    /** @param  list<array{id: int|string}>  $included */
    private function weightedMates(Record $rec, array $included): array
    {
        $excluded = is_array($rec->excluded_days_by_user) ? $rec->excluded_days_by_user : [];
        $guestExtra = is_array($rec->guest_extra_days_by_user) ? $rec->guest_extra_days_by_user : [];
        $billDays = (int) ($rec->bill_period_days ?? 0);
        $gwp = 100.0;

        return array_map(static function ($m) use ($excluded, $guestExtra, $billDays, $gwp) {
            $id = (int) ($m['id'] ?? 0);
            $ex = max(0, (int) ($excluded[$id] ?? 0));
            $gx = max(0, (int) ($guestExtra[$id] ?? 0));
            $guestPart = $gx * ($gwp / 100.0);
            $eff = max(0, $billDays - $ex) + $guestPart;

            return ['id' => $id, 'weight' => $eff];
        }, $included);
    }
}
