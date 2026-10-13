# Prumo — inteligência financeira pessoal e para casais

Plataforma de gestão financeira que transforma dados em decisões: **dinheiro livre** de verdade, **Máquina do Tempo** para simular compras, **GPS Financeiro** para sair das dívidas, **Radar de problemas**, **revisão semanal**, investimentos com dados oficiais e um **consultor com IA** que só usa números calculados pelo sistema.

> O estado real de cada funcionalidade (o que está pronto, parcial ou planejado) está em [`IMPLEMENTATION_STATUS.md`](IMPLEMENTATION_STATUS.md).

## Requisitos
Apenas **Docker Desktop** (com Docker Compose). PHP, Composer, Node, PostgreSQL e Redis rodam em containers.

## Primeira execução
```bash
make setup
```
Abra http://localhost:8080 e crie sua conta. Para explorar com dados fictícios: `make demo` (login `demo@prumo.local` / `demo12345`).

| Serviço | Endereço |
|---|---|
| Aplicação | http://localhost:8080 |
| E-mails de desenvolvimento (Mailpit) | http://localhost:8025 |
| PostgreSQL | localhost:5434 (prumo/prumo) |

Comandos úteis: `make up`, `make down`, `make test`, `make assets`, `make logs`, `make shell`.

## Containers
`app` (Laravel 13 / PHP 8.4) · `scheduler` (tarefas agendadas) · `queue` (filas) · `db` (PostgreSQL 17) · `redis` (cache, sessões, filas) · `mailpit` (e-mail local) · `node` (build dos assets) · `ollama` (opcional, IA local).

## Integrações (todas gratuitas)
| Integração | Precisa de chave? | Observação |
|---|---|---|
| Banco Central (SGS): CDI, Selic, IPCA, poupança | Não | marcação de renda fixa e indicadores |
| BrasilAPI: bancos e feriados | Não | |
| AwesomeAPI: dólar, euro, bitcoin | Não | |
| CoinGecko: criptomoedas | Não | API pública com limite de requisições |
| brapi.dev: ações, FIIs, ETFs da B3 | Opcional | sem token só alguns tickers; token gratuito em https://brapi.dev → `BRAPI_TOKEN` |
| Telegram (bot) | Sim, gratuita | crie no @BotFather → `TELEGRAM_BOT_TOKEN` e `TELEGRAM_BOT_USERNAME` |
| IA — Google Gemini | Sim, plano gratuito | https://aistudio.google.com/apikey → `GEMINI_API_KEY` |
| IA — Groq | Sim, plano gratuito | https://console.groq.com/keys → `GROQ_API_KEY` |
| IA — Ollama (local) | Não | `make ai` (baixa ~2 GB) |
| IA — Anthropic Claude | Sim, **pago** | opcional → `ANTHROPIC_API_KEY` |
| Extratos OFX, CSV, XLSX | — | importação com deduplicação, conciliação e categorização aprendida |

Sem nenhuma chave, o sistema funciona por completo: a IA cai para o **Consultor Offline** (regras locais + os mesmos cálculos determinísticos).

**Open Finance Brasil:** não está integrado. O acesso direto exige ser instituição participante autorizada pelo Banco Central; a alternativa é um agregador comercial (ex.: Pluggy, Belvo), que é pago e exige contrato. Por isso o Prumo oferece importação e conciliação de extratos.

## Segurança
Isolamento de dados por carteira em todas as consultas (escopo global + validação de vínculos), privacidade entre parceiros, 2FA (TOTP) com códigos de recuperação, trilha de auditoria, criptografia de observações/conversas/segredos no banco, cabeçalhos de segurança (CSP, frame-ancestors, nosniff), sessões criptografadas, limites de tentativa no login e no 2FA, exportação de dados (LGPD) e exclusão de conta.

## Testes
```bash
make test
```
Usam um banco separado (`prumo_test`) e bloqueiam qualquer chamada HTTP externa.

## Arquitetura
- `app/Services` — regras de negócio (FinanceAnalytics, FreeMoneyService, TimeMachine, ProblemRadar, DebtPlanner, GoalPlanner, WeeklyReview, TransactionService, RecurrenceService, importação, mercado, IA).
- `app/Http/Controllers` — só orquestram validação → serviço → view.
- `app/Models/Concerns/BelongsToHousehold` — isolamento por carteira e privacidade do casal.
- `resources/views` — Blade + Alpine.js + Tailwind v4 + Chart.js. Convenções em `docs/VIEW-CONVENTIONS.md`.
# prumo
