# 03 — Tabelas Relacionais, Controle de Sessão, i18n e Catálogo Nextcloud App Store

## 1. Esquema Relacional (`lib/Migration/Version0001Date20261121000000.php`)

Ao ativar o aplicativo (`occ app:enable folder_video_player`), o Nextcloud cria automaticamente 4 tabelas prefixadas por `ncvp_` (compatíveis com PostgreSQL, MariaDB/MySQL e SQLite):

1. **`ncvp_folders`**: armazena pastas globais pré-configuradas pelo Admin (`scope = 'global'`) e pastas pessoais por usuário (`scope = 'user'`), com índice único `(scope, user_id, path_hash)`.
2. **`ncvp_playback_progress`**: sincroniza o ponto de retomada de cada vídeo entre diferentes dispositivos da mesma conta Nextcloud, zerando automaticamente após `>= 95%` de conclusão.
3. **`ncvp_user_preferences`**: persiste velocidade (`0.5x`–`2.0x`), reprodução automática do próximo vídeo, critério de ordenação, volume/mudo e idioma preferido de legenda.
4. **`ncvp_playback_sessions`**: controla o ciclo de vida das sessões de reprodução (`active`, `paused`, `ended`, `expired`, `revoked`).

---

## 2. Controle de Sessão e Streaming Sem Bloqueio

- **Abertura e Limite Simultâneo**: `POST /api/v1/sessions` valida permissões na pasta configurada, expira sessões vencidas e verifica `max_concurrent_sessions` (retornando `429 Too Many Requests` se o limite configurado pelo Admin for atingido).
- **Heartbeat e Persistência**: `PUT /api/v1/sessions/{token}/heartbeat` renova `expires_at` e grava o progresso atual em `ncvp_playback_progress`.
- **Liberação de Lock PHP**: `StreamController::stream` valida o `sessionToken` e invoca `OCP\ISession::close()` **antes** de transmitir os chunks HTTP `Range` (`206 Partial Content`), evitando bloqueio de requisições paralelas.
- **Revogação no Logout e Limpeza**: `UserLoggedOutListener` revoga sessões ativas no logout do Nextcloud e `SessionCleanupJob` (`TimedJob` a cada 15 min) expira sessões ociosas e limpa histórico antigo.

---

## 3. Internacionalização Multi-Idioma (i18n)

- **Idioma Padrão/Fallback**: Inglês (`en`) em todas as strings fonte PHP (`OCP\IL10N`), templates e módulos JS (`js/modules/i18n.js`).
- **Português do Brasil (`pt_BR`)**: catálogo completo em `l10n/pt_BR.json` e `l10n/pt_BR.js`, incluindo pluralizações e formatação regional de datas via `Intl.DateTimeFormat`.
- **Extensibilidade**: novos idiomas podem ser adicionados criando `l10n/<locale>.json` e `l10n/<locale>.js` sem alterar regras de negócio.

---

## 4. Publicação no Catálogo Oficial (Nextcloud App Store)

1. `appinfo/info.xml` segue estritamente o XSD `https://apps.nextcloud.com/schema/apps/info.xsd` (categoria `multimedia`, Nextcloud 30–32, PHP 8.2+, metadados em `en` e `pt-br`, capturas de tela em `screenshots/`).
2. Configure os Secrets no GitHub Actions:
   - `NC_APP_PRIVATE_KEY`
   - `NC_APP_CERTIFICATE`
   - `NC_APPSTORE_TOKEN`
3. A cada merge na `main`, o job `release-on-main` gera `appinfo/signature.json`, publica o tarball na GitHub Release e registra a versão em `https://apps.nextcloud.com/api/v1/apps/releases`.
