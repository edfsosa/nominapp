<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $fields = [
            'absence_threshold_minutes' => 30,
            'face_threshold' => 0.45,
            'face_min_confidence_gap' => 0.1,
        ];

        foreach ($fields as $name => $value) {
            DB::table('settings')->insertOrIgnore([
                'group'      => 'general',
                'name'       => $name,
                'payload'    => json_encode($value),
                'locked'     => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('settings')
            ->where('group', 'general')
            ->whereIn('name', ['absence_threshold_minutes', 'face_threshold', 'face_min_confidence_gap'])
            ->delete();
    }
};
