<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BuildAreaPoints extends Command
{
    protected $signature = 'areas:build-points';

    protected $description = 'Dev tool: match union points from the boundary dbf to our areas and write a small CSV';

    private function readDbf(string $path): array
    {
        $raw = file_get_contents($path);
        $count = unpack('V', substr($raw, 4, 4))[1];
        $headLen = unpack('v', substr($raw, 8, 2))[1];
        $recLen = unpack('v', substr($raw, 10, 2))[1];
        $fields = [];
        $off = 1;
        $pos = 32;
        while ($pos + 32 <= $headLen) {
            $d = substr($raw, $pos, 32);
            if ($d[0] === "\r") {
                break;
            }
            $len = ord($d[16]);
            $fields[rtrim(substr($d, 0, 11), "\0")] = [$off, $len];
            $off += $len;
            $pos += 32;
        }
        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $rec = substr($raw, $headLen + $i * $recLen, $recLen);
            if ($rec === '' || $rec[0] === '*') {
                continue;
            }
            $row = [];
            foreach ($fields as $name => [$o, $l]) {
                $row[$name] = trim(substr($rec, $o, $l));
            }
            $rows[] = $row;
        }
        return $rows;
    }

    private function nrm(?string $s): string
    {
        $n = preg_replace('/[^a-z]/', '', strtolower((string) $s));
        $al = [
            'chattagram' => 'chattogram', 'chittagong' => 'chattogram', 'barisal' => 'barishal',
            'comilla' => 'cumilla', 'jhalakathi' => 'jhalokati',
        ];
        return $al[$n] ?? $n;
    }

    /** @return array{0:?int,1:string} */
    private function best(array $cands, string $name): array
    {
        $n = $this->nrm($name);
        if ($n === '') {
            return [null, 'none'];
        }
        foreach ($cands as $id => $c) {
            if ($this->nrm($c) === $n) {
                return [$id, 'exact'];
            }
        }
        $bid = null;
        $bd = 99;
        foreach ($cands as $id => $c) {
            $d = levenshtein($n, $this->nrm($c));
            if ($d < $bd) {
                $bd = $d;
                $bid = $id;
            }
        }
        $lim = strlen($n) >= 7 ? 2 : 1;
        return ($bid !== null && $bd <= $lim) ? [$bid, 'fuzzy'] : [null, 'none'];
    }

    public function handle(): int
    {
        $path = base_path('database/data/bgd_adminpoints.dbf');
        if (! is_file($path)) {
            $this->error('Not found: ' . $path);
            return self::FAILURE;
        }
        $rows = $this->readDbf($path);

        $levels = [];
        foreach ($rows as $r) {
            $l = $r['admin_leve'] ?? ($r['admin_level'] ?? '?');
            $levels[$l] = ($levels[$l] ?? 0) + 1;
        }
        ksort($levels);
        foreach ($levels as $l => $c) {
            $this->line("admin level $l: rows=$c");
        }

        $pts = [];
        $outside = 0;
        foreach ($rows as $r) {
            if (($r['admin_leve'] ?? ($r['admin_level'] ?? '')) !== '4') {
                continue;
            }
            $name = ($r['adm4_name'] ?? '') !== '' ? $r['adm4_name'] : ($r['name'] ?? '');
            $lat = (float) ($r['y_coord'] ?? 0);
            $lng = (float) ($r['x_coord'] ?? 0);
            if ($lat < 20 || $lat > 27 || $lng < 87 || $lng > 93) {
                $outside++;
                continue;
            }
            $pc = $r['adm4_pcode'] ?? '';
            $key = $pc !== '' ? 'p' . $pc : 'k' . $name . '|' . ($r['adm3_name'] ?? '') . '|' . ($r['adm2_name'] ?? '');
            $von = $r['valid_on'] ?? '';
            if (! isset($pts[$key]) || $von > $pts[$key]['von']) {
                $pts[$key] = [
                    'lat' => $lat, 'lng' => $lng, 'name' => $name, 'von' => $von,
                    'a1' => $r['adm1_name'] ?? '', 'a2' => $r['adm2_name'] ?? '', 'a3' => $r['adm3_name'] ?? '',
                ];
            }
        }
        $this->line('union points after dedupe: ' . count($pts) . " (outside Bangladesh: $outside)");

        $nm = []; $div = []; $dis = []; $upa = []; $uni = []; $unionTotal = 0;
        foreach (DB::table('admin_areas')->whereIn('level', ['division', 'district', 'upazila', 'union'])->orderBy('id')->get(['id', 'parent_id', 'level', 'name_en']) as $a) {
            $nm[$a->id] = (string) $a->name_en;
            if ($a->level === 'division') { $div[$a->id] = $a->name_en; }
            elseif ($a->level === 'district') { $dis[$a->parent_id][$a->id] = $a->name_en; }
            elseif ($a->level === 'upazila') { $upa[$a->parent_id][$a->id] = $a->name_en; }
            else { $uni[$a->parent_id][$a->id] = $a->name_en; $unionTotal++; }
        }

        $stat = ['exact' => 0, 'fuzzy' => 0, 'none' => 0];
        $noUpazila = 0; $noUnion = 0;
        $fuzzy = []; $none = []; $used = []; $out = [];
        foreach ($pts as $r) {
            [$dv] = $this->best($div, $r['a1']);
            $ds = $dv ? $this->best($dis[$dv] ?? [], $r['a2'])[0] : null;
            if (! $ds) { $noUpazila++; $none[] = 'DISTRICT? ' . $r['a1'] . ' / ' . $r['a2']; continue; }
            [$up] = $this->best($upa[$ds] ?? [], $r['a3']);
            if (! $up) { $noUpazila++; $none[] = 'UPAZILA? ' . $r['a2'] . ' / ' . $r['a3']; continue; }
            [$uid, $how] = $this->best($uni[$up] ?? [], $r['name']);
            $stat[$how]++;
            if (! $uid) {
                $noUnion++;
                $none[] = 'UNION? ' . $r['a2'] . ' / ' . $r['a3'] . ' / ' . $r['name'];
                continue;
            }
            $used[$uid] = ($used[$uid] ?? 0) + 1;
            if ($how === 'fuzzy') {
                $fuzzy[] = $r['a2'] . ' / ' . $r['a3'] . ' / ' . $r['name'] . ' -> ' . $nm[$uid];
            }
            $out[] = [round($r['lat'], 5), round($r['lng'], 5), $nm[$dv], $nm[$ds], $nm[$up], $nm[$uid]];
        }

        $csv = base_path('database/data/area-points.csv');
        $fh = fopen($csv, 'w');
        fputcsv($fh, ['lat', 'lng', 'division_en', 'district_en', 'upazila_en', 'union_en'], ',', '"', '');
        foreach ($out as $o) {
            fputcsv($fh, $o, ',', '"', '');
        }
        fclose($fh);

        $missing = [];
        foreach ($uni as $upId => $list) {
            foreach ($list as $uid => $n) {
                if (! isset($used[$uid])) { $missing[] = $nm[$upId] . ' / ' . $n; }
            }
        }
        $rep = "FUZZY (check):\n" . implode("\n", $fuzzy)
            . "\n\nNO MATCH:\n" . implode("\n", array_slice($none, 0, 300))
            . "\n\nDB UNIONS WITHOUT POINT (" . count($missing) . "):\n" . implode("\n", $missing) . "\n";
        file_put_contents(storage_path('logs/area-points-report.txt'), $rep);

        $cov = count($used);
        $this->line("MATCH exact={$stat['exact']} fuzzy={$stat['fuzzy']} union-not-found=$noUnion upazila-or-district-not-found=$noUpazila");
        $this->line("DB unions covered: $cov / $unionTotal (" . round($cov * 100 / max(1, $unionTotal)) . '%)');
        $this->line('CSV rows written: ' . count($out) . ' -> database/data/area-points.csv');
        $this->line('Report: storage/logs/area-points-report.txt');

        return self::SUCCESS;
    }
}