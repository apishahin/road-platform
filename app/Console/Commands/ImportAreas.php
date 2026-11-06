<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportAreas extends Command
{
    protected $signature = 'areas:import {file=database/data/bd-areas.csv} {--commit : actually write to the database}';

    protected $description = 'Import upazilas and unions from the CSV (dry-run unless --commit is given)';

    private const ALIASES = [
        'chattagram' => 'chattogram',
        'barisal'    => 'barishal',
        'comilla'    => 'cumilla',
        'coxsbazar'  => "cox's bazar",
        'jhalakathi' => 'jhalokati',
    ];

    private array $map = [];
    private array $createdKeys = [];
    private array $created = ['upazila' => 0, 'union' => 0];
    private int $fakeId = 0;
    private bool $commit = false;

    private function norm(string $s, bool $alias = false): string
    {
        $s = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $s)));
        if ($alias && isset(self::ALIASES[$s])) {
            $s = self::ALIASES[$s];
        }
        return $s;
    }

    private function key(?int $parent, string $level, string $name): string
    {
        return ($parent ?? '') . '|' . $level . '|' . $name;
    }

    /** @return array{0:int,1:bool} id, wasNew */
    private function ensure(int $parent, string $level, string $en, string $bn): array
    {
        $k = $this->key($parent, $level, $this->norm($en));
        if (isset($this->map[$k])) {
            return [$this->map[$k], false];
        }
        $name_bn = trim($bn) !== '' ? trim($bn) : trim($en);
        if ($this->commit) {
            $id = DB::table('admin_areas')->insertGetId([
                'parent_id'  => $parent,
                'level'      => $level,
                'name_bn'    => $name_bn,
                'name_en'    => trim($en),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $id = --$this->fakeId;
        }
        $this->map[$k] = $id;
        $this->createdKeys[$k] = true;
        $this->created[$level]++;
        return [$id, true];
    }

    public function handle(): int
    {
        $this->commit = (bool) $this->option('commit');
        $path = base_path($this->argument('file'));
        if (! is_file($path)) {
            $this->error('File not found: ' . $path);
            return self::FAILURE;
        }

        $h = fopen($path, 'r');
        $header = fgetcsv($h, 0, ',', '"', '');
        if (! $header) {
            $this->error('Empty file');
            return self::FAILURE;
        }
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
        $col = array_flip(array_map('trim', $header));
        foreach (['division_en', 'district_en', 'upazila_en', 'upazila_bn', 'union_en', 'union_bn'] as $need) {
            if (! isset($col[$need])) {
                $this->error('Missing column: ' . $need);
                return self::FAILURE;
            }
        }

        foreach (DB::table('admin_areas')->get(['id', 'parent_id', 'level', 'name_en']) as $a) {
            $alias = in_array($a->level, ['division', 'district'], true);
            $this->map[$this->key($a->parent_id, $a->level, $this->norm((string) $a->name_en, $alias))] = (int) $a->id;
        }

        $rows = 0; $blank = 0; $dupUnion = 0; $bnMissing = 0;
        $missingDiv = []; $missingDist = []; $dupExamples = [];

        if ($this->commit) {
            DB::beginTransaction();
        }

        try {
            while (($r = fgetcsv($h, 0, ',', '"', '')) !== false) {
                if (count($r) < 2 || trim(implode('', $r)) === '') {
                    continue;
                }
                $rows++;
                $div = trim($r[$col['division_en']] ?? '');
                $dis = trim($r[$col['district_en']] ?? '');
                $upa = trim($r[$col['upazila_en']] ?? '');
                $upb = trim($r[$col['upazila_bn']] ?? '');
                $uni = trim($r[$col['union_en']] ?? '');
                $unb = trim($r[$col['union_bn']] ?? '');

                if ($div === '' || $dis === '' || $upa === '' || $uni === '') {
                    $blank++;
                    continue;
                }

                $divId = $this->map[$this->key(null, 'division', $this->norm($div, true))] ?? null;
                if (! $divId) {
                    $missingDiv[$div] = ($missingDiv[$div] ?? 0) + 1;
                    continue;
                }
                $disId = $this->map[$this->key($divId, 'district', $this->norm($dis, true))] ?? null;
                if (! $disId) {
                    $missingDist[$div . ' / ' . $dis] = ($missingDist[$div . ' / ' . $dis] ?? 0) + 1;
                    continue;
                }

                if ($upb === '') { $bnMissing++; }
                if ($unb === '') { $bnMissing++; }

                [$upaId] = $this->ensure($disId, 'upazila', $upa, $upb);
                [, $isNew] = $this->ensure($upaId, 'union', $uni, $unb);

                if (! $isNew) {
                    $k = $this->key($upaId, 'union', $this->norm($uni));
                    if (isset($this->createdKeys[$k])) {
                        $dupUnion++;
                        if (count($dupExamples) < 5) {
                            $dupExamples[] = "$dis / $upa / $uni";
                        }
                    }
                }
            }
            if ($this->commit) {
                DB::commit();
            }
        } catch (\Throwable $e) {
            if ($this->commit) {
                DB::rollBack();
            }
            $this->error('FAILED, nothing saved: ' . $e->getMessage());
            return self::FAILURE;
        } finally {
            fclose($h);
        }

        $this->line('Mode: ' . ($this->commit ? 'COMMIT (written to DB)' : 'DRY-RUN (nothing written)'));
        $this->line("CSV rows read       : $rows");
        $this->line("Rows with blank cell: $blank");
        $this->line("Upazilas new        : {$this->created['upazila']}");
        $this->line("Unions new          : {$this->created['union']}");
        $this->line("Same union twice in file (merged): $dupUnion");
        foreach ($dupExamples as $x) { $this->line('   e.g. ' . $x); }
        $this->line("Blank Bangla names (English used): $bnMissing");
        foreach ($missingDiv as $n => $c) { $this->warn("Division not in DB: $n ($c rows skipped)"); }
        foreach ($missingDist as $n => $c) { $this->warn("District not in DB: $n ($c rows skipped)"); }

        return self::SUCCESS;
    }
}