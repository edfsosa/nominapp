<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_periods', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained()->nullOnDelete();
            // Drop the old unique constraint before the backfill so that inserting
            // new per-company periods doesn't violate the (frequency, start, end) index.
            $table->dropUnique(['frequency', 'start_date', 'end_date']);
        });

        // Backfill: for each period, detect which companies have payrolls in it.
        // If only one company → assign it directly.
        // If multiple companies → keep the original period for the first company and
        // create a new period for each additional company, reassigning their payrolls.
        DB::table('payroll_periods')->get()->each(function ($period) {
            $companies = DB::table('payrolls')
                ->join('employees', 'employees.id', '=', 'payrolls.employee_id')
                ->join('branches', 'branches.id', '=', 'employees.branch_id')
                ->where('payrolls.payroll_period_id', $period->id)
                ->distinct()
                ->pluck('branches.company_id');

            if ($companies->isEmpty()) {
                // No payrolls — leave company_id null, assign manually later.
                return;
            }

            // Assign the first company to the existing period.
            DB::table('payroll_periods')
                ->where('id', $period->id)
                ->update(['company_id' => $companies->first()]);

            // For each additional company, create a new period and reassign its payrolls.
            foreach ($companies->slice(1) as $companyId) {
                $employeeIds = DB::table('payrolls')
                    ->join('employees', 'employees.id', '=', 'payrolls.employee_id')
                    ->join('branches', 'branches.id', '=', 'employees.branch_id')
                    ->where('payrolls.payroll_period_id', $period->id)
                    ->where('branches.company_id', $companyId)
                    ->pluck('payrolls.employee_id');

                $newPeriodId = DB::table('payroll_periods')->insertGetId([
                    'company_id' => $companyId,
                    'name'       => $period->name,
                    'frequency'  => $period->frequency,
                    'start_date' => $period->start_date,
                    'end_date'   => $period->end_date,
                    'status'     => $period->status,
                    'closed_at'  => $period->closed_at,
                    'notes'      => $period->notes,
                    'created_at' => $period->created_at,
                    'updated_at' => $period->updated_at,
                ]);

                DB::table('payrolls')
                    ->where('payroll_period_id', $period->id)
                    ->whereIn('employee_id', $employeeIds)
                    ->update(['payroll_period_id' => $newPeriodId]);
            }
        });

        Schema::table('payroll_periods', function (Blueprint $table) {
            // New constraint: one period per company + frequency + date range.
            $table->unique(['company_id', 'frequency', 'start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        // Drop FK first — MariaDB/MySQL won't allow dropping a unique index
        // that is referenced by a foreign key constraint.
        Schema::table('payroll_periods', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
        });

        Schema::table('payroll_periods', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'frequency', 'start_date', 'end_date']);
            $table->dropColumn('company_id');
            $table->unique(['frequency', 'start_date', 'end_date']);
        });
    }
};
