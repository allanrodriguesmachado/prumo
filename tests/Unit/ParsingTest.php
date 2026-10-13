<?php

namespace Tests\Unit;

use App\Services\Import\Categorizer;
use App\Services\Import\StatementParser;
use Tests\TestCase;

class ParsingTest extends TestCase
{
    public function test_parse_money_and_decimal(): void
    {
        $this->assertSame(1234.56, parse_money('R$ 1.234,56'));
        $this->assertSame(1234.56, parse_money('1234.56'));
        $this->assertSame(1000.0, parse_decimal('1.000'));
        $this->assertSame(0.5, parse_decimal('0,5'));
        $this->assertSame(0.5, parse_decimal('0.5'));
    }

    public function test_ofx_parsing(): void
    {
        $ofx = "OFXHEADER:100\n<OFX><BANKMSGSRSV1><STMTTRNRS><STMTRS><BANKTRANLIST>\n<STMTTRN><TRNTYPE>DEBIT<DTPOSTED>20260305120000[-3:BRT]<TRNAMT>-45.90<FITID>abc1<MEMO>IFOOD *RESTAURANTE</STMTTRN>\n<STMTTRN><TRNTYPE>CREDIT<DTPOSTED>20260305<TRNAMT>3500.00<FITID>abc2<MEMO>PAGTO SALARIO</STMTTRN>\n</BANKTRANLIST></STMTRS></STMTTRNRS></BANKMSGSRSV1></OFX>";
        $r = (new StatementParser)->parse($ofx, 'extrato.ofx');
        $this->assertSame('ofx', $r['format']);
        $this->assertCount(2, $r['rows']);
        $this->assertSame(-45.90, $r['rows'][0]['amount']);
        $this->assertSame('2026-03-05', $r['rows'][0]['date']);
    }

    public function test_csv_parsing_brazilian_format(): void
    {
        $csv = "Data;Descrição;Valor\n05/03/2026;Uber trip;-23,40\n06/03/2026;Pix recebido;1.200,00\n";
        $rows = (new StatementParser)->parse($csv, 'extrato.csv')['rows'];
        $this->assertCount(2, $rows);
        $this->assertSame(-23.40, $rows[0]['amount']);
        $this->assertSame(1200.0, $rows[1]['amount']);
    }

    public function test_rule_key(): void
    {
        $this->assertSame('ifood restaurante', Categorizer::ruleKey('Compra cartão IFOOD *RESTAURANTE 12/03'));
    }
}
