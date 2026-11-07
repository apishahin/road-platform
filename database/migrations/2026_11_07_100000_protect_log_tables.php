<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $tables = ['status_events', 'audit_events'];

    public function up(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        foreach ($this->tables as $t) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$t}_no_update");
            DB::unprepared("DROP TRIGGER IF EXISTS {$t}_no_delete");
            DB::unprepared("CREATE TRIGGER {$t}_no_update BEFORE UPDATE ON {$t} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$t} is append-only'");
            DB::unprepared("CREATE TRIGGER {$t}_no_delete BEFORE DELETE ON {$t} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$t} is append-only'");
        }
    }

    public function down(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        foreach ($this->tables as $t) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$t}_no_update");
            DB::unprepared("DROP TRIGGER IF EXISTS {$t}_no_delete");
        }
    }
};