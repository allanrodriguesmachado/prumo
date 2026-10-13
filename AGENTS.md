# Prumo — guia para agentes

- **Nunca instale PHP, Composer ou Node no sistema operacional.** Tudo roda em Docker Compose.
  - Artisan/Composer: `docker compose exec app php artisan ...` / `docker compose exec app composer ...`
  - Assets: `docker compose run --rm node sh -c "npm install && npm run build"`
  - Testes: `docker compose exec app php artisan test` (usa o banco `prumo_test`)
- Estado e plano: leia `IMPLEMENTATION_STATUS.md` antes de mudar algo e atualize-o ao concluir.
- Convenções de telas: `docs/VIEW-CONVENTIONS.md`.
- Regras de negócio ficam em `app/Services`; controllers só orquestram.
- Todo model financeiro usa `App\Models\Concerns\BelongsToHousehold` (isolamento por carteira).
- A IA nunca recebe ações de escrita; números vêm de serviços determinísticos.
