<?php

namespace App\Services\Import;

use Carbon\Carbon;

/**
 * Lê extratos OFX (padrão de todos os bancos brasileiros) e CSV genérico.
 * Retorna linhas normalizadas: date, description, amount (negativo = saída), fitid.
 */
class StatementParser
{
    public function parse(string $content, string $filename): array
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ($ext === 'xlsx') {
            return ['format' => 'xlsx', 'rows' => $this->tabular($this->readXlsx($content))];
        }
        $content = $this->toUtf8($content);

        if ($ext === 'ofx' || $ext === 'qfx' || str_contains(strtoupper(substr($content, 0, 2000)), '<OFX>')) {
            return ['format' => 'ofx', 'rows' => $this->ofx($content)];
        }

        return ['format' => 'csv', 'rows' => $this->csv($content)];
    }

    private function toUtf8(string $s): string
    {
        if (! mb_check_encoding($s, 'UTF-8')) {
            $s = mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1');
        }

        return preg_replace('/^\xEF\xBB\xBF/', '', $s);
    }

    public function ofx(string $content): array
    {
        $rows = [];
        preg_match_all('/<STMTTRN>(.*?)(?:<\/STMTTRN>|(?=<STMTTRN>)|(?=<\/BANKTRANLIST>))/si', $content, $m);
        foreach ($m[1] as $block) {
            $get = function (string $tag) use ($block) {
                return preg_match('/<'.$tag.'>([^<\r\n]*)/i', $block, $x) ? trim(html_entity_decode($x[1])) : null;
            };
            $amount = (float) str_replace(',', '.', (string) $get('TRNAMT'));
            $date = $get('DTPOSTED');
            if (! $date) {
                continue;
            }
            $desc = trim(($get('MEMO') ?: '').' '.(($get('NAME') && $get('NAME') !== $get('MEMO')) ? $get('NAME') : ''));
            $rows[] = [
                'date' => Carbon::createFromFormat('Ymd', substr($date, 0, 8))->toDateString(),
                'description' => $desc !== '' ? mb_substr($desc, 0, 250) : 'Lançamento importado',
                'amount' => $amount,
                'fitid' => $get('FITID'),
            ];
        }

        return $rows;
    }

    /**
     * CSV flexível: detecta separador e as colunas de data, descrição e valor
     * (ou colunas separadas de crédito/débito), como nos extratos do Nubank, Inter, Itaú etc.
     */
    public function csv(string $content): array
    {
        $lines = array_values(array_filter(preg_split('/\r\n|\n|\r/', $content), fn ($l) => trim($l) !== ''));
        if (count($lines) < 2) {
            return [];
        }
        $sep = collect([';', ',', "\t"])->sortByDesc(fn ($s) => substr_count($lines[0], $s))->first();

        return $this->tabular(array_map(fn ($l) => str_getcsv($l, $sep), $lines));
    }

    /** Lê a primeira planilha de um .xlsx (Excel, Google Planilhas, LibreOffice). */
    private function readXlsx(string $binary): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($tmp, $binary);
        $rows = [];
        try {
            $reader = new \OpenSpout\Reader\XLSX\Reader;
            $reader->open($tmp);
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $cells = array_map(function ($v) {
                        if ($v instanceof \DateTimeInterface) {
                            return $v->format('d/m/Y');
                        }

                        return is_float($v) || is_int($v) ? number_format((float) $v, 2, ',', '') : trim((string) $v);
                    }, $row->toArray());
                    if (array_filter($cells, fn ($c) => $c !== '')) {
                        $rows[] = $cells;
                    }
                }
                break; // só a primeira aba
            }
            $reader->close();
        } finally {
            @unlink($tmp);
        }

        return $rows;
    }

    /** Interpreta uma tabela (cabeçalho + linhas) detectando as colunas. */
    public function tabular(array $table): array
    {
        if (count($table) < 2) {
            return [];
        }
        // o cabeçalho pode não estar na primeira linha (planilhas com título)
        $headerIdx = 0;
        foreach (array_slice($table, 0, 10, true) as $i => $r) {
            $norm = implode(' ', array_map(fn ($h) => Categorizer::normalize((string) $h), $r));
            if (str_contains($norm, 'data') || str_contains($norm, 'date')) {
                $headerIdx = $i;
                break;
            }
        }
        $header = array_map(fn ($h) => Categorizer::normalize((string) $h), $table[$headerIdx]);
        $lines = array_slice($table, $headerIdx);

        $find = function (array $names) use ($header) {
            foreach ($header as $i => $h) {
                foreach ($names as $n) {
                    if (str_contains($h, $n)) {
                        return $i;
                    }
                }
            }

            return null;
        };
        $iDate = $find(['data', 'date']);
        $iDesc = $find(['descri', 'historico', 'title', 'titulo', 'estabelecimento', 'lancamento', 'memo']);
        $iAmount = $find(['valor', 'amount', 'quantia', 'montante']);
        $iCredit = $find(['credito', 'entrada']);
        $iDebit = $find(['debito', 'saida']);
        if ($iDate === null || ($iAmount === null && $iCredit === null)) {
            return [];
        }
        // fatura de cartão (ex.: Nubank) vem com valores positivos para compras
        $isCardInvoice = in_array('title', $header) || in_array('category', $header);

        $rows = [];
        foreach (array_slice($lines, 1) as $c) {
            $c = array_map(fn ($v) => (string) $v, $c);
            $date = $this->parseDate($c[$iDate] ?? '');
            if (! $date) {
                continue;
            }
            if ($iAmount !== null) {
                $amount = parse_money($c[$iAmount] ?? 0);
                if ($isCardInvoice) {
                    $amount = -$amount;
                }
            } else {
                $amount = parse_money($c[$iCredit] ?? 0) - abs(parse_money($c[$iDebit] ?? 0));
            }
            if (abs($amount) < 0.005) {
                continue;
            }
            $rows[] = [
                'date' => $date, 'description' => mb_substr(trim($c[$iDesc] ?? 'Lançamento importado'), 0, 250),
                'amount' => $amount, 'fitid' => null,
            ];
        }

        return $rows;
    }

    private function parseDate(string $v): ?string
    {
        $v = trim($v);
        foreach (['d/m/Y', 'Y-m-d', 'd-m-Y', 'd/m/y', 'd.m.Y'] as $f) {
            try {
                $d = Carbon::createFromFormat($f, substr($v, 0, strlen(date($f))));
                if ($d && $d->year > 1990) {
                    return $d->toDateString();
                }
            } catch (\Throwable) {
            }
        }

        return null;
    }
}
