<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $tables = ['status_events', 'audit_events'];

    private function run(string $sql): void
    {
        try {
            DB::unprepared($sql);
        } catch (\Throwable $e) {
            // Shared hosting may forbid triggers. Model guards still protect these rows.
        }
    }

    public function up(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        foreach ($this->tables as $t) {
            $this->run("DROP TRIGGER IF EXISTS {$t}_no_update");
            $this->run("CREATE TRIGGER {$t}_no_update BEFORE UPDATE ON {$t} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$t} is append-only'");
            $this->run("DROP TRIGGER IF EXISTS {$t}_no_delete");
            $this->run("CREATE TRIGGER {$t}_no_delete BEFORE DELETE ON {$t} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$t} is append-only'");
        }
    }

    public function down(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        foreach ($this->tables as $t) {
            $this->run("DROP TRIGGER IF EXISTS {$t}_no_update");
            $this->run("DROP TRIGGER IF EXISTS {$t}_no_delete");
        }
    }
};