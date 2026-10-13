<?php

namespace App\Services\Ai;

use App\Services\Import\Categorizer;

/**
 * Consultor 100% local, sem IA externa: identifica a intenção da pergunta
 * e responde com regras de educação financeira usando os números reais.
 */
class OfflineAdvisor
{
    public function answer(string $question, array $ctx, array $tools = []): string
    {
        $byTool = collect($tools)->keyBy('tool');
        if ($byTool->has('maquina_do_tempo')) {
            return $this->simulation($byTool['maquina_do_tempo']['result']).$this->footer();
        }
        $q = ' '.Categorizer::normalize($question).' ';
        $intents = [
            'debt' => ['divida', 'devendo', 'negativ', 'serasa', 'juros', 'emprestimo', 'quitar', 'negociar', 'cheque especial', 'rotativo', 'atrasad'],
            'invest' => ['invest', 'aplicar', 'render', 'tesouro', 'cdb', 'acoes', 'acao ', 'fii', 'cripto', 'bitcoin', 'aposentad'],
            'emergency' => ['reserva', 'emergencia', 'imprevisto'],
            'save' => ['economizar', 'economia', 'gastando', 'gasto muito', 'cortar', 'sobrar', 'poupar', 'guardar'],
            'card' => ['cartao', 'fatura', 'limite'],
            'couple' => ['casal', 'esposa', 'marido', 'namorad', 'parceir', 'dividir'],
            'daily' => ['hoje', 'posso gastar', 'por dia'],
            'score' => ['score', 'nota', 'saude financeira'],
        ];
        $intent = 'overview';
        foreach ($intents as $name => $words) {
            foreach ($words as $w) {
                if (str_contains($q, $w)) {
                    $intent = $name;
                    break 2;
                }
            }
        }

        $body = match ($intent) {
            'debt' => $this->debt($ctx),
            'invest' => $this->invest($ctx),
            'emergency' => $this->emergency($ctx),
            'save' => $this->save($ctx),
            'card' => $this->card($ctx),
            'couple' => $this->couple($ctx),
            'daily' => $this->daily($ctx),
            'score' => $this->scoreText($ctx),
            default => $this->overview($ctx),
        };

        if ($intent === 'overview' && $byTool->has('projecao')) {
            $body = $this->projectionText($byTool['projecao']['result']);
        }
        if ($byTool->has('comparar_categorias') && in_array($intent, ['overview', 'save'])) {
            $body = $this->whyText($byTool['comparar_categorias']['result'])."\n\n".$body;
        }

        return $body.$this->footer();
    }

    private function footer(): string
    {
        return "\n\n---\n_Resposta do **Consultor Offline** do Prumo (regras locais, sem IA externa; todos os números vêm dos cálculos do sistema). Configure uma chave gratuita do Gemini ou Groq — ou rode o Ollama — para conversas mais completas._";
    }

    private function simulation(array $r): string
    {
        $lines = ['**Simulei essa decisão nos próximos 12 meses** com base no que está cadastrado:', ''];
        $b = $r['sem_a_decisao'];
        $lines[] = "- **Sem a compra:** menor saldo de ".brl($b['menor_saldo'])." em {$b['mes_do_menor_saldo']}.";
        foreach ($r['cenarios'] as $c) {
            $icon = ['viável' => '🟢', 'apertado' => '🟡', 'inviável' => '🔴'][$c['veredito']] ?? '•';
            $lines[] = "- {$icon} **{$c['cenario']}** — {$c['veredito']}: {$c['explicacao']}".($c['compromete_da_renda_pct'] ? ' Compromete '.pct($c['compromete_da_renda_pct'], 0).' da renda no mês mais pesado.' : '');
        }
        $lines[] = '';
        $lines[] = '**Premissas:** '.implode(' ', $r['premissas']);
        $lines[] = '';
        $lines[] = 'Abra a **Máquina do Tempo** para ver o gráfico mês a mês e testar outras opções.';

        return implode("\n", $lines);
    }

    private function projectionText(array $rows): string
    {
        $lines = ['**Projeção do seu saldo** (contas cadastradas, fixos, parcelas, faturas e média de gastos):', ''];
        foreach ($rows as $r) {
            $lines[] = "- {$r['mes']}: entradas ".brl($r['entradas']).', saídas '.brl($r['saidas']).' → saldo **'.brl($r['saldo_projetado']).'**';
        }
        $lines[] = '';
        $lines[] = '_É uma estimativa: gastos não cadastrados e imprevistos mudam o resultado._';

        return implode("\n", $lines);
    }

    private function whyText(array $r): string
    {
        $up = collect($r['categorias'])->where('diferenca', '>', 0)->take(3);
        if ($up->isEmpty()) {
            return 'Comparando com o mês passado, nenhuma categoria subiu até agora ('.$r['dias_decorridos_mes_atual'].' dias do mês).';
        }

        return "**O que mais subiu em relação ao mês passado** ({$r['dias_decorridos_mes_atual']} dias do mês):\n"
            .$up->map(fn ($c) => "- {$c['categoria']}: ".brl($c['mes_atual']).' (antes '.brl($c['mes_anterior']).', +'.brl($c['diferenca']).')')->implode("\n");
    }

    private function overview(array $c): string
    {
        $m = $c['mes_atual'];
        $lines = ["Olá, {$c['primeiro_nome']}! Aqui vai um raio-x rápido:", ''];
        $lines[] = '- **Renda mensal estimada:** '.brl($c['renda_mensal_estimada']);
        $lines[] = '- **Gastos neste mês:** '.brl($m['expense']).' (fixos '.brl($m['fixed']).', parcelas '.brl($m['installments']).', dia a dia '.brl($m['daily']).')';
        $lines[] = '- **Saldo previsto do mês:** '.brl($m['balance']);
        $lines[] = "- **Prumo Score:** {$c['prumo_score']}";
        $lines[] = '';
        $lines[] = '**Próximos passos sugeridos:**';
        foreach ($this->priorities($c) as $i => $p) {
            $lines[] = ($i + 1).'. '.$p;
        }

        return implode("\n", $lines);
    }

    private function priorities(array $c): array
    {
        $p = [];
        $overdue = collect($c['dividas'])->where('em_atraso', true);
        $expensive = collect($c['dividas'])->where('juros_am', '>', 5);
        if ($overdue->count()) {
            $p[] = 'Você tem **'.$overdue->count().' dívida(s) em atraso**. Consulte o Registrato (Banco Central) e negocie começando pela que tem maior juros ou risco de corte de serviço.';
        }
        if ($expensive->count()) {
            $p[] = 'Troque dívidas caras ('.$expensive->pluck('credor')->implode(', ').') por crédito mais barato (consignado, portabilidade) ou negocie desconto à vista.';
        }
        if ($c['mes_atual']['balance'] < 0) {
            $p[] = 'O mês está fechando **no vermelho em '.brl(abs($c['mes_atual']['balance'])).'**. Revise as categorias de estilo de vida antes de novas compras.';
        }
        if (count($c['assinaturas_detectadas'])) {
            $p[] = 'Revise as assinaturas recorrentes detectadas: '.implode('; ', array_slice($c['assinaturas_detectadas'], 0, 3)).'.';
        }
        if (! collect($c['metas'])->count()) {
            $p[] = 'Crie uma meta de **reserva de emergência** (6x seus gastos essenciais ≈ '.brl($c['gastos_fixos_mensais'] * 6).').';
        }
        $p[] = 'Defina orçamentos por categoria e acompanhe o "quanto posso gastar hoje" no painel.';

        return array_slice($p, 0, 4);
    }

    private function debt(array $c): string
    {
        $debts = collect($c['dividas']);
        if ($debts->isEmpty()) {
            return 'Você não tem dívidas cadastradas. 🎉 Se tiver alguma, cadastre em **Plano de Dívidas** para o Prumo montar a estratégia de quitação. Dica: consulte gratuitamente o **Registrato** do Banco Central para ver todas as dívidas no seu CPF.';
        }
        $total = $debts->sum('saldo');
        $interest = $debts->sum(fn ($d) => $d['saldo'] * $d['juros_am'] / 100);
        $sorted = $debts->sortByDesc('juros_am');
        $lines = [
            'Suas dívidas somam **'.brl($total).'** e geram cerca de **'.brl($interest).' de juros por mês**.',
            '',
            '**Ordem de ataque sugerida (método Prumo):**',
        ];
        foreach ($sorted->values() as $i => $d) {
            $lines[] = ($i + 1).". {$d['credor']} — ".brl($d['saldo'])." a {$d['juros_am']}% a.m.".($d['em_atraso'] ? ' ⚠️ em atraso' : '');
        }
        $lines[] = '';
        $lines[] = '**Como negociar:**';
        $lines[] = '- Ligue ou use o app do credor e peça a proposta de **pagamento à vista com desconto** (descontos de 40% a 90% são comuns em dívidas antigas).';
        $lines[] = '- Use o **Serasa Limpa Nome** e mutirões de negociação; verifique se há edição do **Desenrola**.';
        $lines[] = '- Nunca aceite parcela que não caiba no orçamento — proponha um valor que você consegue pagar.';
        $lines[] = '- Se as dívidas comprometem o mínimo para viver, a **Lei do Superendividamento (14.181/2021)** permite repactuar tudo com apoio do Procon ou Defensoria Pública.';
        if ($c['plano_dividas']) {
            $pd = $c['plano_dividas'];
            $lines[] = '';
            $lines[] = $pd['feasible']
                ? "Com **".brl($pd['valor_mensal'])."/mês** no seu plano atual, você fica livre em **{$pd['months']} meses**, pagando ".brl($pd['interest']).' de juros.'
                : 'Com o valor mensal atual o plano **não fecha** (os juros crescem mais rápido). Aumente o valor ou negocie as taxas primeiro.';
        }

        return implode("\n", $lines);
    }

    private function invest(array $c): string
    {
        $hasDebt = collect($c['dividas'])->where('juros_am', '>', 2)->count() > 0;
        $lines = [];
        if ($hasDebt) {
            $lines[] = '⚠️ Antes de investir: você tem dívidas com juros acima de 2% a.m. Nenhum investimento seguro rende isso — **quitar essas dívidas é o melhor "investimento" agora**.';
            $lines[] = '';
        }
        $lines[] = '**Escada de investimentos (do mais seguro ao mais arriscado):**';
        $lines[] = '1. **Reserva de emergência** em Tesouro Selic ou CDB com liquidez diária (≥100% do CDI) — meta de 6 meses de gastos essenciais.';
        $lines[] = '2. **Objetivos de médio prazo** (2–5 anos): CDB, LCI/LCA, Tesouro IPCA+ com vencimento próximo da meta.';
        $lines[] = '3. **Longo prazo** (5+ anos): Tesouro IPCA+, ETFs de índice, fundos imobiliários e ações, diversificando.';
        $lines[] = '';
        $lines[] = 'Seu patrimônio investido hoje: **'.brl($c['patrimonio']['investments']).'**. Comece com um aporte automático todo mês logo que o salário cair ("pague-se primeiro").';
        $lines[] = '_O Prumo não é consultoria de valores mobiliários registrada na CVM; use estas orientações como educação financeira._';

        return implode("\n", $lines);
    }

    private function emergency(array $c): string
    {
        $target = max($c['gastos_fixos_mensais'], $c['gasto_medio_mensal_3m'] * 0.7) * 6;

        return "Sua **reserva de emergência ideal** é de cerca de **".brl($target)."** (6 meses dos gastos essenciais).\n\n"
            ."- Guarde em algo seguro e com liquidez diária: Tesouro Selic ou CDB de banco grande com 100% do CDI.\n"
            ."- Se tiver dívida cara, monte primeiro uma mini-reserva de 1 mês (".brl($target / 6).") e depois foque nas dívidas.\n"
            ."- Crie a meta \"Reserva de emergência\" no Prumo e automatize um aporte mensal.";
    }

    private function save(array $c): string
    {
        $lines = ['**Onde está indo seu dinheiro este mês:**'];
        foreach (array_slice($c['gastos_por_categoria_mes'], 0, 5) as $l) {
            $lines[] = "- {$l}";
        }
        $lines[] = '';
        $lines[] = '**Regra 50/30/20 no seu caso:** essenciais '.($c['regra_50_30_20']['needs'] ?? '-').', estilo de vida '.($c['regra_50_30_20']['wants'] ?? '-').', futuro '.($c['regra_50_30_20']['savings'] ?? '-').'.';
        $lines[] = '';
        $lines[] = '**Táticas que funcionam:**';
        $lines[] = '- Regra das 48h: espere 2 dias antes de qualquer compra não planejada acima de '.brl(max(100, $c['renda_mensal_estimada'] * 0.03)).'.';
        $lines[] = '- Defina teto para delivery/restaurantes e acompanhe no painel.';
        $lines[] = '- Cancele assinaturas pouco usadas'.(count($c['assinaturas_detectadas']) ? ': '.implode('; ', array_slice($c['assinaturas_detectadas'], 0, 3)) : '').'.';
        $lines[] = '- Renegocie internet, celular e seguros uma vez por ano.';

        return implode("\n", $lines);
    }

    private function card(array $c): string
    {
        return "**Cartão de crédito sem sustos:**\n\n"
            ."- Use no máximo 30% do limite e **sempre pague o total da fatura** — o rotativo passa de 10% ao mês.\n"
            ."- Se não conseguir pagar tudo, o parcelamento da fatura costuma ser mais barato que o rotativo; melhor ainda é um empréstimo pessoal/consignado com juros menores.\n"
            ."- Faturas abertas hoje: ".brl($c['patrimonio']['cards']).".\n"
            ."- Parcelamentos ativos comprometem sua renda futura: confira o **Radar de Parcelas** no painel.";
    }

    private function couple(array $c): string
    {
        return "**Dinheiro a dois sem brigas:**\n\n"
            ."- Dividir as contas da casa **proporcionalmente à renda** costuma ser o mais justo (configure em Casal → Divisão).\n"
            ."- Mantenham uma parte individual para cada um gastar sem dar satisfação.\n"
            ."- Façam uma \"reunião financeira\" de 20 minutos por mês olhando o painel do Prumo juntos.\n"
            ."- Tenham metas em comum (viagem, casa, reserva) — o Prumo mostra o progresso dos dois.";
    }

    private function daily(array $c): string
    {
        $s = $c['pode_gastar_por_dia'];

        return $s['negative']
            ? "Este mês já está comprometido além da renda prevista (faltam ".brl(abs($s['free'])).'). O ideal é **segurar gastos não essenciais** até o próximo recebimento.'
            : 'Você pode gastar cerca de **'.brl($s['per_day']).' por dia** até o fim do mês ('.$s['days_left'].' dias), já reservando '.brl($s['saving_target']).' para poupar. Hoje ainda restam **'.brl($s['today_left']).'**.';
    }

    private function scoreText(array $c): string
    {
        return "Seu **Prumo Score** é {$c['prumo_score']}.\n\n".implode("\n", array_map(fn ($p) => "- {$p}", $c['pilares_score']));
    }
}
