<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFeeToAdditionalFeesTable extends Migration
{
    public function up()
    {
        Schema::table('additional_fees', function (Blueprint $table) {
            $table->double('fee')->default(0)->after('title');
        });
    }

    public function down()
    {
        Schema::table('additional_fees', function (Blueprint $table) {
            $table->dropColumn('fee');
        });
    }
}
