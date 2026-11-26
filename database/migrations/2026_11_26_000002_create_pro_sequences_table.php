<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pro_sequences', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();
        });

        $lastNumber = DB::table('lab_requests')
            ->whereNotNull('pro_number')
            ->pluck('pro_number')
            ->reduce(function (int $currentMax, ?string $proNumber): int {
                if (! $proNumber || ! preg_match('/^PRO-(\d+)$/', $proNumber, $matches)) {
                    return $currentMax;
                }

                return max($currentMax, (int) $matches[1]);
            }, 0);

        DB::table('pro_sequences')->insert([
            'id' => 1,
            'last_number' => $lastNumber,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('pro_sequences');
    }
};
