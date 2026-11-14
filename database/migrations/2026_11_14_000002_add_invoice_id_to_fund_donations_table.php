<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fund_donations', function (Blueprint $t) {
            // UddoktaPay invoice id, stored when the gateway calls back.
            $t->string('invoice_id', 64)->nullable()->after('tran_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('fund_donations', function (Blueprint $t) {
            $t->dropIndex(['invoice_id']);
            $t->dropColumn('invoice_id');
        });
    }
};
