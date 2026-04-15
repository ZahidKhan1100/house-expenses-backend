<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private function catalogLabels(): array
    {
        return [
            'toilet_paper' => 'Toilet paper',
            'milk' => 'Milk',
            'cooking_oil' => 'Cooking oil',
        ];
    }

    public function up(): void
    {
        Schema::table('house_running_low_requests', function (Blueprint $table) {
            $table->string('display_label', 64)->default('')->after('item_key');
        });

        $map = $this->catalogLabels();
        $rows = DB::table('house_running_low_requests')->get(['id', 'item_key']);
        foreach ($rows as $row) {
            $label = $map[$row->item_key] ?? (string) $row->item_key;
            DB::table('house_running_low_requests')->where('id', $row->id)->update([
                'display_label' => $label,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('house_running_low_requests', function (Blueprint $table) {
            $table->dropColumn('display_label');
        });
    }
};
