<?php

namespace App\Http\Controllers;

use App\Models\AiConversation;
use App\Services\Ai\AiAdvisor;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AiController extends Controller
{
    public const SUGGESTIONS = [
        'Faça um diagnóstico completo das minhas finanças',
        'Como saio das dívidas o mais rápido possível?',
        'Quanto posso gastar por dia até o fim do mês?',
        'Onde estou gastando demais?',
        'Como monto minha reserva de emergência?',
        'Por onde começo a investir com pouco dinheiro?',
        'Monte um plano para eu economizar R$ 500 por mês',
        'Como dividir as contas de forma justa com meu parceiro(a)?',
    ];

    public function index(Request $request, AiAdvisor $advisor, ?AiConversation $conversation = null)
    {
        $user = $request->user();
        $conversations = AiConversation::where('user_id', $user->id)->where('household_id', $user->current_household_id)->latest('updated_at')->take(30)->get();
        if ($conversation) {
            abort_unless($conversation->user_id === $user->id, 404);
            $conversation->load('messages');
        }

        return view('ai.index', [
            'conversations' => $conversations,
            'conversation' => $conversation,
            'suggestions' => self::SUGGESTIONS,
            'providers' => $advisor->availableProviders(),
        ]);
    }

    public function send(Request $request, AiAdvisor $advisor)
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
            'conversation_id' => ['nullable', 'integer'],
        ]);
        $user = $request->user();

        $conversation = ! empty($data['conversation_id'])
            ? AiConversation::where('user_id', $user->id)->findOrFail($data['conversation_id'])
            : AiConversation::create(['user_id' => $user->id, 'household_id' => $user->current_household_id, 'title' => Str::limit($data['message'], 60)]);

        $conversation->messages()->create(['role' => 'user', 'content' => $data['message']]);
        $history = $conversation->messages()->latest('id')->take(16)->get()->reverse()
            ->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])->values()->all();

        $reply = $advisor->reply($user, $history);
        $msg = $conversation->messages()->create(['role' => 'assistant', 'content' => $reply['content'], 'provider' => $reply['provider']]);
        $conversation->touch();

        if ($request->expectsJson()) {
            return response()->json([
                'conversation_id' => $conversation->id,
                'url' => route('ai.index', $conversation),
                'content' => $msg->content,
                'html' => Str::markdown($msg->content, ['html_input' => 'escape', 'allow_unsafe_links' => false]),
                'provider' => $msg->provider,
            ]);
        }

        return redirect()->route('ai.index', $conversation);
    }

    public function destroy(Request $request, AiConversation $conversation)
    {
        abort_unless($conversation->user_id === $request->user()->id, 404);
        $conversation->delete();

        return redirect()->route('ai.index')->with('success', 'Conversa apagada.');
    }
}
