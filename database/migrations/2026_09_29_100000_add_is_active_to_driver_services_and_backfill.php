<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * driver_services.status    : 1 = approved by admin, 0 = requested by driver (pending review), 2 = rejected/unapproved by admin
 * driver_services.is_active : driver's own day/session choice among approved services
 *
 * users.service_id is kept as the driver's "primary" service for backward compatibility.
 */
class AddIsActiveToDriverServicesAndBackfill extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('driver_services', 'is_active')) {
            Schema::table('driver_services', function (Blueprint $table) {
                $table->tinyInteger('is_active')->default(1)->after('status');
                $table->index(['service_id', 'status', 'is_active'], 'driver_services_match_index');
            });
        }

        // Existing single-service drivers keep their service as approved + active.
        DB::statement("
            INSERT INTO driver_services (driver_id, service_id, status, is_active, created_at, updated_at)
            SELECT u.id, u.service_id, 1, 1, NOW(), NOW()
            FROM users u
            WHERE u.user_type = 'driver'
              AND u.service_id IS NOT NULL
              AND NOT EXISTS (
                  SELECT 1 FROM driver_services ds
                  WHERE ds.driver_id = u.id AND ds.service_id = u.service_id
              )
        ");

        // Rows that already existed for the same pair but were never approved-flagged.
        DB::statement("
            UPDATE driver_services ds
            JOIN users u ON u.id = ds.driver_id AND u.service_id = ds.service_id
            SET ds.status = 1
            WHERE u.user_type = 'driver' AND (ds.status IS NULL OR ds.status = 0)
        ");
    }

    public function down()
    {
        if (Schema::hasColumn('driver_services', 'is_active')) {
            Schema::table('driver_services', function (Blueprint $table) {
                $table->dropIndex('driver_services_match_index');
                $table->dropColumn('is_active');
            });
        }
    }
}
