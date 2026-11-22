# Proposal

## Why

O projeto [lan-video-player](https://github.com/castrocristiano/lan-video-player) resolveu com sucesso a reprodução leve, rápida e 100% offline de bibliotecas de vídeos na rede local, mas opera como um servidor HTTP standalone fora do ecossistema Nextcloud. Usuários que já mantêm acervos de vídeos no Nextcloud (incluindo pastas compartilhadas e montagens de *External Storage*) precisam de uma experiência equivalente **integrada nativamente ao Nextcloud**, com **controle formal de sessão**, **persistência relacional em tabelas do banco do Nextcloud** (para pastas configuradas, progresso sincronizado entre dispositivos, preferências e sessões ativas) e pronta para **distribuição oficial no catálogo da Nextcloud App Store (`apps.nextcloud.com`)** — sempre restrita às **pastas selecionadas já configuradas** e sem jamais modificar arquivos nas pastas de mídia.

## What Changes

Introduz o aplicativo Nextcloud **`nextcloud_video_player`** (compatível com **Nextcloud 30–32**, **PHP 8.2+** e frontend modular em **Vanilla JS + CSS**), portando e adaptando a experiência de biblioteca e player do `lan-video-player` para dentro do Nextcloud:

- **Persistência relacional em tabelas dedicadas do Nextcloud**:
  - Criação e versionamento via migrações oficiais do Nextcloud (compatíveis com PostgreSQL, MariaDB/MySQL e SQLite) de tabelas próprias (`ncvp_folders`, `ncvp_playback_progress`, `ncvp_user_preferences`, `ncvp_playback_sessions`) para armazenar pastas globais/pessoais, progresso sincronizado entre dispositivos, preferências do player e registro de sessões ativas/histórico.
  - `OCP\IConfig` fica restrito a parâmetros operacionais globais de infraestrutura definidos pelo administrador (ex.: habilitação/caminho do `ffmpeg`, TTL padrão de sessão e limite de sessões simultâneas).
- **Configuração híbrida de pastas selecionadas**:
  - Administradores pré-configuram pastas globais/compartilhadas nas Configurações de Administração do Nextcloud, visíveis automaticamente apenas para usuários que possuam permissão nativa de leitura naquela pasta no Nextcloud.
  - Cada usuário também pode selecionar, adicionar ou desvincular pastas adicionais da sua própria árvore do Nextcloud através de um seletor visual de pastas no app.
- **Controle formal de sessão e concorrência**:
  - Sessão autenticada do Nextcloud obrigatória em todas as rotas (`401` para anônimos/expirados), proteção CSRF nativa nas operações mutáveis e isolamento estrito por `user_id`.
  - Sessões de reprodução rastreadas em banco com criação, *heartbeat* periódico (renovando TTL e persistindo posição assistida), encerramento explícito (`keepalive`/`sendBeacon`), expiração automática por inatividade, revogação automática ao fazer logout no Nextcloud e limite configurável pelo administrador de sessões/transcodificações simultâneas por usuário.
  - Liberação explícita do *lock* de sessão PHP (`OCP\ISession::close()`) antes de transmitir chunks de vídeo ou pipe do `ffmpeg` para evitar bloqueio de requisições simultâneas do usuário.
- **Catálogo somente-leitura, Streaming HTTP Range e Player Completo**:
  - Listagem hierárquica via `OCP\Files\IRootFolder` com *breadcrumbs*, busca por nome em todas as pastas configuradas, ordenação e miniaturas sem gravar nenhum arquivo nas pastas de mídia.
  - Streaming com `HTTP Range` (`206 Partial Content`) e fallback opcional de transcodificação em memória via `ffmpeg`, player estilo YouTube em tela cheia, velocidade (`0.5x`–`2.0x`), atalhos de teclado/TV, legendas locais (`.vtt` e conversão em memória de `.srt`), seleção de faixa de áudio, retomada sincronizada no servidor e próximo vídeo automático com contagem regressiva cancelável.
- **Publicação no catálogo oficial do Nextcloud (Nextcloud App Store)**:
  - Metadados `appinfo/info.xml` validados estritamente contra o XSD oficial da App Store (`multimedia`, dependências Nextcloud 30–32 e PHP 8.2+, screenshots, licença `agpl`), empacotamento limpo somente-runtime, assinatura criptográfica de integridade (`appinfo/signature.json` + assinatura SHA-512 do tarball via segredos no CI) e publicação automatizada na API da App Store vinculada à GitHub Release.
- **Internacionalização (i18n) multi-idioma**:
  - Arquitetura baseada em `OCP\IL10N` e catálogos `l10n/` nativos do Nextcloud preparada para múltiplos idiomas sem alteração de código-fonte, adotando **Inglês (`en`) como idioma padrão/fallback** e entregando tradução completa em **Português do Brasil (`pt_BR`)** desde a v1 (inclusive nos metadados do catálogo `appinfo/info.xml`).
- **Princípios inegociáveis preservados**:
  - Operação **estritamente somente-leitura** sobre todas as pastas de mídia configuradas.
  - Funcionamento **100% offline / zero CDNs externos**, compatível com `Content-Security-Policy` estrita do Nextcloud e licenciado sob GNU AGPL v3.0.

### Out of Scope (Non-Goals)

- Edição, renomeação, upload, exclusão ou movimentação de arquivos de vídeo pela interface do plugin.
- Download automático de metadados, capas ou sinopses em serviços externos na internet (TMDB/OMDB).
- Compartilhamento público anônimo por link fora de sessões autenticadas do Nextcloud na v1.
- Migração para framework Vue/Webpack pesado ou microserviço externo via AppAPI na v1.

## Capabilities

### New Capabilities

- `folder-configuration`: Gerenciamento híbrido de pastas selecionadas (pastas pré-configuradas globalmente pelo Admin e pastas pessoais selecionadas por cada usuário), com seletor visual, validação de permissões nativas do Nextcloud e persistência relacional fora das pastas de mídia.
- `session-and-persistence`: Persistência relacional em tabelas dedicadas do Nextcloud (pastas, progresso, preferências e sessões) via migrações oficiais e controle completo do ciclo de vida de sessões de reprodução, segurança, logout, limites simultâneos e streaming sem bloqueio de sessão PHP.
- `video-catalog`: Navegação hierárquica sobre as pastas configuradas, filtragem de arquivos de vídeo, *breadcrumbs*, busca textual em toda a biblioteca ativa, ordenação e exibição de miniaturas em modo estritamente somente-leitura.
- `video-streaming-player`: Streaming progressivo via HTTP Range (`206 Partial Content`), inspeção e transcodificação opcional em memória via `ffmpeg`, e player HTML5 responsivo com tela cheia estilo YouTube, legendas locais `.vtt`/`.srt`, faixas de áudio, retomada sincronizada e próximo vídeo automático.
- `internationalization`: Suporte nativo e extensível a múltiplos idiomas via catálogos `l10n/` do Nextcloud, com Inglês (`en`) como padrão/fallback e Português do Brasil (`pt_BR`) completo na v1.
- `appstore-distribution`: Conformidade, empacotamento limpo, assinatura criptográfica de integridade e publicação automatizada do aplicativo no catálogo oficial da Nextcloud App Store (`apps.nextcloud.com`).

### Modified Capabilities

<!-- Nenhuma capacidade existente está sendo modificada (projeto greenfield). -->

## Impact

- **Metadados, Bootstrap e Catálogo App Store**: Criação de `appinfo/info.xml` (validado contra `info.xsd`, com metadados em inglês `en` padrão e `pt_BR`), `appinfo/routes.php`, `lib/AppInfo/Application.php`, `img/app.svg`, `screenshots/`, `l10n/` (`pt_BR.json`, `pt_BR.js`) e `CHANGELOG.md` para Nextcloud 30–32 (`nextcloud_video_player`).
- **Banco de Dados e Migrações (`lib/Migration/`, `lib/Db/`)**: Migração inicial criando `ncvp_folders`, `ncvp_playback_progress`, `ncvp_user_preferences` e `ncvp_playback_sessions` com Entities e Mappers tipados (`OCP\AppFramework\Db\QBMapper`).
- **Backend PHP (`lib/`)**: Controllers (`PageController`, `ConfigController`, `CatalogController`, `StreamController`, `SessionController`, `PreferenceController`, `SettingsController`), serviços (`FolderConfigService`, `SafeNodeResolver`, `VideoCatalogService`, `SubtitleService`, `MediaStreamService`, `FfmpegService`, `PlaybackSessionService`, `ProgressService`, `PreferenceService`), internacionalização via `OCP\IL10N`, listener de logout (`UserLoggedOutListener`) e job de limpeza (`SessionCleanupJob`).
- **Frontend Vanilla JS/CSS (`js/`, `css/`, `templates/`)**: Interface principal do catálogo/player internacionalizada (`js/modules/i18n.js` com `en` padrão e `pt_BR`), gerenciamento de sessão (*heartbeat*, `keepalive`/`sendBeacon`, listagem/revogação de sessões), sincronização de progresso/preferências com a API, painel de configurações de Admin e tema integrado ao Nextcloud.
- **CI/CD, Testes e Qualidade (`.github/workflows/ci.yml`, `tests/`, `composer.json`, `package.json`)**: Validação XSD de `appinfo/info.xml`, suítes PHPUnit (Clover) e Vitest (LCOV), PHPStan, ESLint, SonarQube Quality Gate, empacotamento assinado e publicação condicional na Nextcloud App Store.
