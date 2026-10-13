# Prumo — Estado real da implementação

> Documento vivo. ✅ = implementado **e** coberto por teste automatizado ou verificado manualmente (anotado).
> 🟡 parcial (ver nota) · ⏳ planejado · ❌ não viável / fora de escopo (com motivo)

Última atualização: 2026-10-09

## Stack efetiva
- Laravel 13 (PHP 8.4) · Blade + Alpine.js 3 + Tailwind CSS v4 + Chart.js 4 (Vite)
- PostgreSQL 17 · Redis 7 (cache, sessões criptografadas, filas)
- Docker Compose: `app`, `scheduler`, `queue`, `db`, `redis`, `mailpit`, `node` (build), `ollama` (perfil opcional `ai`)
- Nada instalado no sistema operacional além do Docker. Inicialização: `make setup` (ver README).

## Testes
`make test` (ou `docker compose exec app php artisan test`) — banco separado `prumo_test`, chamadas HTTP externas bloqueadas.
**Última execução: 51 testes, 354 asserções, todos passando (2026-10-09).**

| Suíte | Cobre |
|---|---|
| AuthTest | cadastro, senha fraca, login/logout, redirecionamentos, onboarding |
| IsolationTest | outra carteira não vê/edita/exclui registros; não referencia conta alheia; não troca para carteira alheia |
| SecurityTest | 2FA completo (QR, ativação, desafio, recuperação de uso único, segredo criptografado), privacidade no casal, auditoria sem dados sensíveis, criptografia de observações, cabeçalhos/CSP, exportação LGPD isolada, rate limit de login |
| TransactionFlowTest | parcelas com centavos exatos, fatura certa por fechamento/vencimento, pagamento de fatura, transferência, pagamento de dívida e estorno, baixa, duplo envio bloqueado |
| CardOperationsTest | pagamento parcial (saldo vai para a próxima fatura), pagamento acima do saldo, antecipação de parcelas com desconto |
| RecurrenceTest | geração mensal, dia 31 em meses curtos, sem duplicar, edição só altera futuros pendentes |
| IntelligenceTest | Dinheiro Livre (período até o próximo salário, contas, faturas, metas), Máquina do Tempo (vereditos), Radar (saldo negativo, vencidas, comprometimento, dinheiro livre negativo), metas inviáveis, roteador de ferramentas da IA, endpoint da IA sem alterar dados, revisão semanal |
| ImportReconciliationTest | OFX com conciliação de lançamento existente, reimportação sem duplicar, categorização, XLSX com cabeçalho deslocado, extrato com saldo corrido |
| PagesRenderTest | **todas** as telas renderizam (vazio, individual com dados, casal com dados e privacidade da IA, telas públicas) |
| Unit | planejador de dívidas, parsers OFX/CSV, `parse_money`/`parse_decimal`, `Money` (centavos inteiros) |

Verificação manual (navegador, 2026-10-09): login, painel, Máquina do Tempo, modo escuro e layout em 375 px sem rolagem horizontal; e-mails de alerta e de revisão semanal chegando no Mailpit; integrações externas respondendo ao vivo.

## Inventário

### Conta, segurança e LGPD
| Item | Estado | Nota |
|---|---|---|
| Cadastro, login, "lembrar-me", recuperação de senha | ✅ | throttle 10/min no login |
| 2FA TOTP + 8 códigos de recuperação | ✅ | segredo e códigos criptografados; proteção contra reuso do código |
| Trilha de auditoria | ✅ | login/falhas/2FA/senha/exportação + criar/alterar/excluir de todos os registros financeiros; exibida no perfil |
| Criptografia de campos sensíveis | ✅ | observações de lançamentos e dívidas, conversas com a IA, segredos 2FA; sessões criptografadas |
| Cabeçalhos de segurança | ✅ | CSP, X-Frame-Options, nosniff, Referrer-Policy, Permissions-Policy, HSTS fora de dev, no-store em páginas logadas |
| Exportação de dados (JSON) e exclusão de conta | ✅ | no casal, o histórico é transferido ao parceiro |
| Isolamento por carteira | ✅ | escopo global em todos os models + validação de vínculos |
| Casal: privacidade | ✅ | contas, cartões, lançamentos, dívidas, metas e investimentos podem ser privados; lançamento em conta privada herda privacidade e nunca entra no rateio |
| Casal: papéis/permissões diferentes por membro | ⏳ | hoje os dois membros têm os mesmos poderes sobre itens não privados |
| Prevenção de lançamento duplicado | ✅ | botão desabilita no envio + bloqueio no servidor (10 s) + hash de importação |
| Precisão monetária | 🟡 | `decimal(14,2)` no banco; divisões de parcelas e somas críticas em centavos inteiros (`App\Support\Money`); demais cálculos analíticos usam float com arredondamento a 2 casas |

### Módulos financeiros
| Item | Estado | Nota |
|---|---|---|
| Receitas: salário fixo, renda extra, outras, recorrentes (semanal/mensal/anual), previstas | ✅ | |
| Despesas: dia a dia, fixas, parceladas, recorrentes, compartilhadas, privadas | ✅ | |
| Contas: múltiplas, saldo realizado e previsto, transferências | ✅ | |
| Extrato por conta com saldo corrido + conferência manual | ✅ | |
| Conciliação automática na importação | ✅ | mesmo valor, ±3 dias, conta/cartão alvo |
| Cartões: limite, fechamento, vencimento, faturas, parcelas | ✅ | |
| Cartões: pagamento parcial e antecipação de parcelas | ✅ | saldo não pago vira lançamento "rotativo" na fatura seguinte |
| Orçamentos por categoria, sugestão automática, 50/30/20, alertas | ✅ | |
| Dívidas: cadastro, juros, pagamentos, 3 estratégias + mínimo, roteiro de negociação | ✅ | |
| Metas: aportes/resgates, viabilidade, data realista | ✅ | |
| Investimentos: ações/FIIs/ETFs/BDR/cripto/renda fixa/Tesouro/poupança, proventos, preço médio | ✅ | renda fixa = valor bruto estimado (antes de IR) |
| Simuladores: juros compostos, independência financeira, custo de oportunidade | ✅ | |
| Evolução patrimonial histórica (série no tempo) | ⏳ | hoje há patrimônio atual e saldo projetado; falta registrar fotos mensais do patrimônio |
| Calendário com feriados, relatórios, exportação CSV | ✅ | |

### Diferenciais
| Item | Estado | Nota |
|---|---|---|
| A. Dinheiro Livre | ✅ | período até o próximo salário; separa contas a pagar, faturas, metas, plano de dívidas e meta de poupança; composição explicada no painel |
| B. Máquina do Tempo | ✅ | compra (parcelado × à vista × juntar e comprar), nova despesa/renda mensal, perda de renda; premissas explícitas |
| C. GPS Financeiro | ✅ | plano + comparação + checagem de essenciais + histórico diário do saldo devedor; sem promessa de resultado |
| D. Radar de Problemas | ✅ | 9 regras (saldo negativo, parcelas, comprometimento, queda de renda, categoria, fixo fora do padrão, assinaturas, vencimentos, dinheiro livre) |
| E. Casal inteligente | 🟡 | divisão igual/proporcional/combinada, acerto, metas conjuntas e privacidade ✅; permissões por papel ⏳ |
| F. Metas inteligentes | ✅ | capacidade de poupança real dividida entre metas; status viável/apertado/inviável |
| G. Revisão semanal | ✅ | página + envio às segundas 8h30 (e-mail, Telegram, notificação) |
| Prumo Score, custo em horas de trabalho, radar de parcelas | ✅ | |

### IA
| Item | Estado | Nota |
|---|---|---|
| Ferramentas determinísticas (Máquina do Tempo, Dinheiro Livre, projeção, comparação de categorias, plano de dívidas, 50/30/20, radar) | ✅ | executadas no backend a partir da pergunta; a IA recebe os resultados e é instruída a não inventar números |
| Consultor Offline (sem IA externa) usando as mesmas ferramentas | ✅ | |
| Provedores Gemini, Groq, Ollama, Anthropic com fallback em cadeia | 🟡 | código pronto; **não testado com chaves reais nesta máquina** |
| IA nunca altera dados | ✅ | não existe nenhuma ação de escrita disponível para a IA (testado) |
| Function calling nativo dos provedores | ⏳ | hoje as ferramentas são escolhidas por regras a partir da pergunta |

### Integrações (validadas ao vivo em 2026-10-09)
| Serviço | Estado |
|---|---|
| Banco Central SGS (CDI 12, Selic 11/432, IPCA 433/13522, CDI anual 4389, poupança 7828) | ✅ |
| BrasilAPI (bancos, feriados) | ✅ |
| brapi.dev (B3) | ✅ sem token só tickers de teste |
| CoinGecko, AwesomeAPI | ✅ |
| Telegram (long polling, sem URL pública) | 🟡 código pronto e agendado; exige criar bot |
| E-mail (alertas, revisão semanal) | ✅ via Mailpit em dev; configure SMTP real em produção |
| Importação OFX, CSV, XLSX | ✅ |
| Open Finance Brasil | ❌ exige ser instituição autorizada pelo BCB ou contratar agregador pago (Pluggy, Belvo) |

### Experiência
| Item | Estado | Nota |
|---|---|---|
| Modo claro/escuro (sistema ou manual) | ✅ | identidade visual na paleta do Laravel (vermelho `#F53003` + neutros quentes, fonte Instrument Sans) — verificada no navegador em claro, escuro e 375 px (2026-10-13) |
| Responsivo / mobile | ✅ | navegação inferior no celular, grades por container query (sem aperto em notebooks com a barra lateral); varredura sem rolagem horizontal em 375 px e 768 px nas telas principais e checagem visual em 375/768/1100/1440 px (2026-10-13) |
| Estados de carregamento nos formulários | ✅ | |
| Acessibilidade: link "pular para o conteúdo", `aria-*` em botões de ícone, foco visível, `prefers-reduced-motion` | 🟡 | sem auditoria completa com leitor de tela |
| PWA / app instalável | ⏳ | |

## Próximos passos sugeridos
1. Permissões por papel no casal (somente leitura, aprovação de despesas compartilhadas).
2. Fotos mensais do patrimônio (evolução patrimonial histórica).
3. Function calling nativo nos provedores de IA que suportam.
4. Auditoria de acessibilidade com leitor de tela e testes E2E de navegador.
5. Guia de deploy em produção (HTTPS, SMTP real, backups do PostgreSQL).
