<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class ImportReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private function ofx(string $body): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('extrato.ofx', "<OFX><BANKTRANLIST>{$body}</BANKTRANLIST></OFX>");
    }

    public function test_import_reconciles_existing_and_skips_duplicates_on_reimport(): void
    {
        $this->userWithHousehold();
        $acc = Account::first();
        $manual = Transaction::create(['type' => 'expense', 'kind' => 'fixed', 'description' => 'Conta de luz', 'amount' => 187.40, 'date' => '2026-09-14', 'paid' => false, 'account_id' => $acc->id]);

        $file = $this->ofx('<STMTTRN><DTPOSTED>20260915<TRNAMT>-187.40<FITID>1<MEMO>ENEL SP</STMTTRN><STMTTRN><DTPOSTED>20260916<TRNAMT>-32.90<FITID>2<MEMO>UBER TRIP</STMTTRN>');
        $this->post(route('imports.preview'), ['file' => $file, 'method' => 'account:'.$acc->id])->assertOk()->assertSee('concilia com');

        $this->post(route('imports.store'), ['method' => 'account:'.$acc->id, 'rows' => [0 => ['selected' => '1'], 1 => ['selected' => '1']]])->assertRedirect();

        $manual->refresh();
        $this->assertTrue($manual->paid);
        $this->assertNotNull($manual->reconciled_at);
        $this->assertSame(2, Transaction::count(), 'luz conciliada (não duplicada) + uber criado');
        $this->assertSame('Transporte', Transaction::where('description', 'UBER TRIP')->first()->category?->name);

        // reimportar o mesmo arquivo não duplica nada
        $this->post(route('imports.preview'), ['file' => $this->ofx('<STMTTRN><DTPOSTED>20260915<TRNAMT>-187.40<FITID>1<MEMO>ENEL SP</STMTTRN><STMTTRN><DTPOSTED>20260916<TRNAMT>-32.90<FITID>2<MEMO>UBER TRIP</STMTTRN>'), 'method' => 'account:'.$acc->id])->assertSee('já importado');
        $this->post(route('imports.store'), ['method' => 'account:'.$acc->id, 'rows' => [0 => ['selected' => '1'], 1 => ['selected' => '1']]]);
        $this->assertSame(2, Transaction::count());
    }

    public function test_xlsx_import(): void
    {
        $this->userWithHousehold();
        $acc = Account::first();
        $path = tempnam(sys_get_temp_dir(), 'x').'.xlsx';
        $w = new Writer;
        $w->openToFile($path);
        $w->addRow(Row::fromValues(['Minha planilha de gastos']));
        $w->addRow(Row::fromValues(['Data', 'Descrição', 'Valor']));
        $w->addRow(Row::fromValues(['05/09/2026', 'Mercado Extra', -245.3]));
        $w->addRow(Row::fromValues(['06/09/2026', 'Freela', 900]));
        $w->close();

        $file = new UploadedFile($path, 'gastos.xlsx', null, null, true);
        $this->post(route('imports.preview'), ['file' => $file, 'method' => 'account:'.$acc->id])->assertOk()->assertSee('Mercado Extra');
        $this->post(route('imports.store'), ['method' => 'account:'.$acc->id, 'rows' => [0 => ['selected' => '1'], 1 => ['selected' => '1']]]);
        $this->assertEquals(245.30, (float) Transaction::where('description', 'Mercado Extra')->value('amount'));
        $this->assertSame('income', Transaction::where('description', 'Freela')->value('type'));
    }

    public function test_statement_running_balance_and_reconcile_toggle(): void
    {
        $this->userWithHousehold();
        $acc = Account::first();
        $acc->update(['initial_balance' => 100]);
        $t = Transaction::create(['type' => 'income', 'kind' => 'extra_income', 'description' => 'Pix', 'amount' => 50, 'date' => now()->toDateString(), 'account_id' => $acc->id]);
        $this->get(route('accounts.show', $acc))->assertOk()->assertSee('R$ 150,00');
        $this->patch(route('accounts.reconcile', [$acc, $t]))->assertRedirect();
        $this->assertNotNull($t->fresh()->reconciled_at);
    }
}
