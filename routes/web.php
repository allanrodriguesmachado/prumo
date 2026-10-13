<?php

use App\Http\Controllers;
use App\Http\Controllers\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check() ? redirect()->route('dashboard') : view('welcome'))->name('home');

Route::get('/convite/{code}', [Controllers\OnboardingController::class, 'invite'])->name('invite');

Route::middleware('guest')->group(function () {
    Route::get('/entrar', [Auth\AuthController::class, 'showLogin'])->name('login');
    Route::post('/entrar', [Auth\AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::get('/cadastro', [Auth\AuthController::class, 'showRegister'])->name('register');
    Route::post('/cadastro', [Auth\AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::get('/esqueci-senha', [Auth\PasswordController::class, 'request'])->name('password.request');
    Route::post('/esqueci-senha', [Auth\PasswordController::class, 'email'])->name('password.email')->middleware('throttle:5,1');
    Route::get('/redefinir-senha/{token}', [Auth\PasswordController::class, 'reset'])->name('password.reset');
    Route::post('/redefinir-senha', [Auth\PasswordController::class, 'update'])->name('password.update');
    Route::get('/entrar/verificacao', [Auth\TwoFactorController::class, 'challenge'])->name('two-factor.challenge');
    Route::post('/entrar/verificacao', [Auth\TwoFactorController::class, 'verify'])->name('two-factor.verify')->middleware('throttle:6,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/sair', [Auth\AuthController::class, 'logout'])->name('logout');

    Route::get('/comecar', [Controllers\OnboardingController::class, 'show'])->name('onboarding');
    Route::post('/comecar', [Controllers\OnboardingController::class, 'store'])->name('onboarding.store');
    Route::post('/comecar/entrar-casal', [Controllers\OnboardingController::class, 'join'])->name('onboarding.join');

    Route::middleware('household')->group(function () {
        Route::get('/painel', [Controllers\DashboardController::class, 'index'])->name('dashboard');
        Route::get('/primeiros-passos', [Controllers\OnboardingController::class, 'setup'])->name('setup');
        Route::post('/primeiros-passos', [Controllers\OnboardingController::class, 'saveSetup'])->name('setup.store');

        // Lançamentos
        Route::get('/lancamentos/exportar', [Controllers\TransactionController::class, 'export'])->name('transactions.export');
        Route::patch('/lancamentos/{transaction}/pago', [Controllers\TransactionController::class, 'togglePaid'])->name('transactions.toggle');
        Route::resource('lancamentos', Controllers\TransactionController::class)->parameters(['lancamentos' => 'transaction'])->names('transactions')->except('show');

        // Renda e gastos fixos (recorrências)
        Route::resource('fixos', Controllers\RecurrenceController::class)->parameters(['fixos' => 'recurrence'])->names('recurrences')->except('show');

        // Contas, cartões e categorias
        Route::patch('/contas/{account}/conciliar/{transaction}', [Controllers\AccountController::class, 'reconcile'])->name('accounts.reconcile');
        Route::resource('contas', Controllers\AccountController::class)->parameters(['contas' => 'account'])->names('accounts');
        Route::get('/cartoes/{card}/fatura', [Controllers\CreditCardController::class, 'invoice'])->name('cards.invoice');
        Route::post('/cartoes/{card}/pagar', [Controllers\CreditCardController::class, 'pay'])->name('cards.pay');
        Route::post('/lancamentos/{transaction}/antecipar', [Controllers\CreditCardController::class, 'anticipate'])->name('cards.anticipate');
        Route::resource('cartoes', Controllers\CreditCardController::class)->parameters(['cartoes' => 'card'])->names('cards')->except('show');
        Route::resource('categorias', Controllers\CategoryController::class)->parameters(['categorias' => 'category'])->names('categories')->except('show');
        Route::get('/orcamentos', [Controllers\BudgetController::class, 'index'])->name('budgets.index');
        Route::post('/orcamentos', [Controllers\BudgetController::class, 'update'])->name('budgets.update');
        Route::post('/orcamentos/sugerir', [Controllers\BudgetController::class, 'suggest'])->name('budgets.suggest');

        // Dívidas
        Route::get('/dividas/negociacao/{debt}', [Controllers\DebtController::class, 'negotiation'])->name('debts.negotiation');
        Route::post('/dividas/plano', [Controllers\DebtController::class, 'plan'])->name('debts.plan');
        Route::post('/dividas/{debt}/pagar', [Controllers\DebtController::class, 'pay'])->name('debts.pay');
        Route::resource('dividas', Controllers\DebtController::class)->parameters(['dividas' => 'debt'])->names('debts')->except('show');

        // Metas
        Route::post('/metas/{goal}/aportar', [Controllers\GoalController::class, 'contribute'])->name('goals.contribute');
        Route::resource('metas', Controllers\GoalController::class)->parameters(['metas' => 'goal'])->names('goals')->except('show');

        // Investimentos
        Route::get('/investimentos', [Controllers\InvestmentController::class, 'index'])->name('investments.index');
        Route::get('/investimentos/simuladores', [Controllers\InvestmentController::class, 'simulators'])->name('investments.simulators');
        Route::get('/investimentos/tickers', [Controllers\InvestmentController::class, 'tickers'])->name('investments.tickers');
        Route::post('/investimentos/atualizar', [Controllers\InvestmentController::class, 'refresh'])->name('investments.refresh');
        Route::get('/investimentos/ativos/novo', [Controllers\InvestmentController::class, 'create'])->name('investments.create');
        Route::post('/investimentos/ativos', [Controllers\InvestmentController::class, 'store'])->name('investments.store');
        Route::get('/investimentos/ativos/{asset}', [Controllers\InvestmentController::class, 'show'])->name('investments.show');
        Route::get('/investimentos/ativos/{asset}/editar', [Controllers\InvestmentController::class, 'edit'])->name('investments.edit');
        Route::put('/investimentos/ativos/{asset}', [Controllers\InvestmentController::class, 'update'])->name('investments.update');
        Route::delete('/investimentos/ativos/{asset}', [Controllers\InvestmentController::class, 'destroy'])->name('investments.destroy');
        Route::post('/investimentos/ativos/{asset}/operacoes', [Controllers\InvestmentController::class, 'storeOperation'])->name('investments.operations.store');
        Route::delete('/investimentos/operacoes/{operation}', [Controllers\InvestmentController::class, 'destroyOperation'])->name('investments.operations.destroy');

        // IA
        Route::get('/consultor/{conversation?}', [Controllers\AiController::class, 'index'])->name('ai.index');
        Route::post('/consultor/mensagem', [Controllers\AiController::class, 'send'])->name('ai.send')->middleware('throttle:20,1');
        Route::delete('/consultor/{conversation}', [Controllers\AiController::class, 'destroy'])->name('ai.destroy');

        // Inteligência
        Route::get('/maquina-do-tempo', [Controllers\IntelligenceController::class, 'timeMachine'])->name('time-machine.index');
        Route::get('/radar', [Controllers\IntelligenceController::class, 'radar'])->name('radar.index');
        Route::get('/revisao-semanal', [Controllers\IntelligenceController::class, 'weekly'])->name('weekly.index');

        // Relatórios, calendário, importação
        Route::get('/relatorios', [Controllers\ReportController::class, 'index'])->name('reports.index');
        Route::get('/calendario', [Controllers\CalendarController::class, 'index'])->name('calendar.index');
        Route::get('/importar', [Controllers\ImportController::class, 'index'])->name('imports.index');
        Route::post('/importar/previa', [Controllers\ImportController::class, 'preview'])->name('imports.preview');
        Route::post('/importar', [Controllers\ImportController::class, 'store'])->name('imports.store');

        // Casal
        Route::get('/casal', [Controllers\CoupleController::class, 'index'])->name('couple.index');
        Route::put('/casal', [Controllers\CoupleController::class, 'update'])->name('couple.update');
        Route::post('/casal/aprovar/{transaction}', [Controllers\CoupleController::class, 'approve'])->name('couple.approve');
        Route::post('/casal/acertar', [Controllers\CoupleController::class, 'settle'])->name('couple.settle');
        Route::post('/carteira/{household}/ativar', [Controllers\CoupleController::class, 'switch'])->name('household.switch');

        // Perfil e notificações
        Route::get('/perfil', [Controllers\ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('/perfil', [Controllers\ProfileController::class, 'update'])->name('profile.update');
        Route::put('/perfil/senha', [Controllers\ProfileController::class, 'password'])->name('profile.password');
        Route::post('/perfil/telegram', [Controllers\ProfileController::class, 'telegram'])->name('profile.telegram');
        Route::delete('/perfil', [Controllers\ProfileController::class, 'destroy'])->name('profile.destroy');
        Route::get('/perfil/exportar', [Controllers\ProfileController::class, 'export'])->name('profile.export')->middleware('throttle:5,1');
        Route::get('/perfil/2fa', [Auth\TwoFactorController::class, 'setup'])->name('two-factor.setup');
        Route::post('/perfil/2fa', [Auth\TwoFactorController::class, 'enable'])->name('two-factor.enable')->middleware('throttle:10,1');
        Route::delete('/perfil/2fa', [Auth\TwoFactorController::class, 'disable'])->name('two-factor.disable');
        Route::post('/perfil/2fa/codigos', [Auth\TwoFactorController::class, 'regenerate'])->name('two-factor.regenerate');
        Route::post('/notificacoes/lidas', [Controllers\ProfileController::class, 'readNotifications'])->name('notifications.read');
    });
});
