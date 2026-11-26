<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('test_results', function (Blueprint $table) {
            $table->string('matrix_type', 16)->nullable()->after('last_updated_by_id');
        });

        DB::table('lab_request_test_items')
            ->where('selected', true)
            ->orderBy('id')
            ->chunkById(500, function ($requestTestItems) {
                $now = now();
                $results = $requestTestItems->map(fn ($item) => [
                    'lab_request_test_item_id' => $item->id,
                    'status' => 'pending',
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                if ($results !== []) {
                    DB::table('test_results')->insertOrIgnore($results);
                }
            });
    }

    public function down(): void
    {
        Schema::table('test_results', function (Blueprint $table) {
            $table->dropColumn('matrix_type');
        });
    }
};
