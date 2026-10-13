<?php

namespace App\Services\Ai;

use Anthropic\Client as AnthropicClient;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Consultor financeiro com IA. Tenta os provedores configurados em ordem
 * (Gemini e Groq têm plano gratuito; Ollama roda local e grátis) e, se
 * nenhum estiver disponível, usa o Consultor Offline baseado em regras.
 */
class AiAdvisor
{
    public function __construct(private FinancialContext $context, private OfflineAdvisor $offline) {}

    /** Executa as ferramentas determinísticas para a última pergunta. */
    public function tools(string $question): array
    {
        return (new ToolRouter(\App\Services\FinanceAnalytics::current()))->run($question);
    }

    public static function systemPrompt(): string
    {
        return <<<'TXT'
Você é o Prumo, um consultor financeiro pessoal brasileiro, acolhedor e direto ao ponto.
Seu papel: ajudar a pessoa (ou o casal) a organizar o orçamento, sair das dívidas, montar reserva de emergência e começar a investir com segurança.

Como responder:
- Português do Brasil, linguagem simples, sem julgamentos. Dívida não é falha de caráter.
- Use SEMPRE os números reais do contexto financeiro fornecido. Cite valores em R$.
- Seja prático: termine com 1 a 3 próximos passos concretos e com prazo.
- Ordem de prioridades: (1) despesas essenciais e contas em atraso que geram corte de serviço, (2) parar de criar novas dívidas, (3) quitar/negociar dívidas caras (rotativo, cheque especial), (4) reserva de emergência, (5) investir.
- Para dívidas, conheça e sugira quando fizer sentido: Registrato do Banco Central (consultar dívidas no CPF, gratuito), Serasa Limpa Nome e feirões de negociação, programa Desenrola (quando disponível), portabilidade de crédito, troca de dívida cara por consignado, Lei do Superendividamento (Lei 14.181/2021) com apoio do Procon/Defensoria, e roteiros de negociação.
- Para investimentos, fale de classes (Tesouro Selic, CDB com FGC, LCI/LCA, fundos imobiliários, ações, ETFs) e de perfil de risco. Não recomende um ativo específico como "compre X"; você não é um consultor de valores mobiliários registrado na CVM — diga isso quando a pergunta exigir.
- Se for um casal, considere justiça na divisão (proporcional à renda) e conversas sem culpa.
- Formatação: Markdown leve (negrito, listas curtas). Respostas de até ~300 palavras, a menos que peçam detalhes.

Regras inegociáveis sobre números:
- NUNCA invente valores. Use somente os números do CONTEXTO FINANCEIRO e dos RESULTADOS DE FERRAMENTAS (calculados pelo sistema).
- Não refaça contas que as ferramentas já fizeram; explique o resultado e as premissas.
- Se faltar um dado para responder (ex.: renda não cadastrada, dívida sem taxa), diga exatamente o que falta e onde cadastrar.
- Deixe claro que projeções não são garantias.
- Você NÃO altera, cria nem exclui dados. Se o usuário pedir, explique em qual tela do Prumo ele mesmo pode fazer isso.
TXT;
    }

    /** @param array<int, array{role:string, content:string}> $history */
    public function reply(User $user, array $history): array
    {
        $context = $this->context->asText($user);
        $last = collect($history)->where('role', 'user')->last()['content'] ?? '';
        $tools = $this->tools($last);
        $system = self::systemPrompt()."\n\nHoje é ".now()->format('d/m/Y').".\nCONTEXTO FINANCEIRO (JSON):\n".$context
            .($tools ? "\n\nRESULTADOS DE FERRAMENTAS para a última pergunta (JSON, calculados pelo sistema):\n".json_encode($tools, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : '');

        foreach (config('prumo.ai.order') as $provider) {
            try {
                $text = match ($provider) {
                    'gemini' => $this->gemini($system, $history),
                    'groq' => $this->groq($system, $history),
                    'ollama' => $this->ollama($system, $history),
                    'anthropic' => $this->anthropic($system, $history),
                    default => null,
                };
                if (is_string($text) && trim($text) !== '') {
                    return ['content' => trim($text), 'provider' => $provider, 'tools' => array_column($tools, 'tool')];
                }
            } catch (\Throwable $e) {
                Log::warning("AI provider {$provider} falhou: ".$e->getMessage());
            }
        }

        return ['content' => $this->offline->answer($last, $this->context->build($user), $tools), 'provider' => 'offline', 'tools' => array_column($tools, 'tool')];
    }

    public function availableProviders(): array
    {
        $out = [];
        foreach (config('prumo.ai.order') as $p) {
            $ok = match ($p) {
                'gemini' => (bool) config('prumo.ai.gemini.key'),
                'groq' => (bool) config('prumo.ai.groq.key'),
                'anthropic' => (bool) config('prumo.ai.anthropic.key'),
                'ollama' => $this->ollamaUp(),
                default => false,
            };
            if ($ok) {
                $out[] = $p;
            }
        }

        return $out;
    }

    private function gemini(string $system, array $history): ?string
    {
        $cfg = config('prumo.ai.gemini');
        if (! $cfg['key']) {
            return null;
        }
        $res = Http::timeout(60)->withHeaders(['x-goog-api-key' => $cfg['key']])
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$cfg['model']}:generateContent", [
                'system_instruction' => ['parts' => [['text' => $system]]],
                'contents' => array_map(fn ($m) => ['role' => $m['role'] === 'assistant' ? 'model' : 'user', 'parts' => [['text' => $m['content']]]], $history),
                'generationConfig' => ['temperature' => 0.6, 'maxOutputTokens' => 4096],
            ])->throw();

        return collect($res->json('candidates.0.content.parts') ?? [])->pluck('text')->implode('');
    }

    private function groq(string $system, array $history): ?string
    {
        $cfg = config('prumo.ai.groq');
        if (! $cfg['key']) {
            return null;
        }
        $res = Http::timeout(60)->withToken($cfg['key'])->post('https://api.groq.com/openai/v1/chat/completions', [
            'model' => $cfg['model'],
            'temperature' => 0.6,
            'max_tokens' => 2048,
            'messages' => array_merge([['role' => 'system', 'content' => $system]], $history),
        ])->throw();

        return $res->json('choices.0.message.content');
    }

    private function ollama(string $system, array $history): ?string
    {
        if (! $this->ollamaUp()) {
            return null;
        }
        $cfg = config('prumo.ai.ollama');
        $res = Http::timeout(180)->post(rtrim($cfg['url'], '/').'/api/chat', [
            'model' => $cfg['model'],
            'stream' => false,
            'messages' => array_merge([['role' => 'system', 'content' => $system]], $history),
        ])->throw();

        return $res->json('message.content');
    }

    private function ollamaUp(): bool
    {
        $url = config('prumo.ai.ollama.url');
        if (! $url) {
            return false;
        }

        return cache()->remember('ollama:up', 60, function () use ($url) {
            try {
                $tags = Http::timeout(2)->get(rtrim($url, '/').'/api/tags')->json('models') ?? [];
                $model = config('prumo.ai.ollama.model');

                return collect($tags)->contains(fn ($m) => str_starts_with($m['name'] ?? '', explode(':', $model)[0]));
            } catch (\Throwable) {
                return false;
            }
        });
    }

    private function anthropic(string $system, array $history): ?string
    {
        $cfg = config('prumo.ai.anthropic');
        if (! $cfg['key']) {
            return null;
        }
        $client = new AnthropicClient(apiKey: $cfg['key']);
        $message = $client->messages->create(
            model: $cfg['model'],
            maxTokens: 16000,
            system: $system,
            messages: array_map(fn ($m) => ['role' => $m['role'], 'content' => $m['content']], $history),
        );
        // recusa do classificador de segurança: segue para o próximo provedor
        $stop = $message->stopReason ?? null;
        if (($stop instanceof \BackedEnum ? $stop->value : $stop) === 'refusal') {
            return null;
        }
        $text = '';
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }
        }

        return $text;
    }
}
