# Proposal

## Why

O projeto [lan-video-player](https://github.com/castrocristiano/lan-video-player) resolveu com sucesso a reprodução leve, rápida e 100% offline de bibliotecas de vídeos na rede local, mas opera como um servidor HTTP standalone fora do ecossistema Nextcloud. Usuários que já mantêm acervos de vídeos no Nextcloud (incluindo pastas compartilhadas e montagens de *External Storage*) precisam de uma experiência equivalente **integrada nativamente ao Nextcloud**, aproveitando autenticação, permissões por usuário e temas claro/escuro, porém restrita apenas às **pastas selecionadas já configuradas** — sem misturar vídeos pessoais aleatórios de toda a nuvem e sem jamais modificar ou gravar arquivos nas pastas de mídia.

## What Changes

Introduz o aplicativo Nextcloud **`nextcloud_video_player`** (compatível com **Nextcloud 30–32**, **PHP 8.2+** e frontend modular em **Vanilla JS + CSS**), portando e adaptando a experiência de biblioteca e player do `lan-video-player` para dentro do Nextcloud:

- **Configuração híbrida de pastas selecionadas**:
  - Administradores pré-configuram pastas globais/compartilhadas nas Configurações de Administração do Nextcloud (armazenadas via `OCP\IConfig` em escopo de app), visíveis automaticamente apenas para usuários que possuam permissão nativa de leitura naquela pasta no Nextcloud.
  - Cada usuário também pode selecionar, adicionar ou desvincular pastas adicionais da sua própria árvore do Nextcloud através de um seletor visual de pastas no app (armazenadas em escopo de usuário no `OCP\IConfig`).
- **Catálogo somente-leitura e navegação rápida**:
  - Listagem hierárquica de pastas configuradas e subpastas via `OCP\Files\IRootFolder`, exibindo apenas subpastas e arquivos de vídeo reconhecidos, com *breadcrumbs*, busca por nome (insensível a maiúsculas/minúsculas), ordenação (nome e data de modificação) e miniaturas sem gravar nenhum arquivo nas pastas de mídia.
- **Streaming HTTP Range e Player Completo**:
  - Endpoint autenticado de streaming com suporte completo a `HTTP Range` (`206 Partial Content`) para início imediato e *seek* sem download prévio completo, além de fallback opcional de transcodificação em memória via `ffmpeg` (quando habilitado pelo administrador e disponível no servidor) para formatos legados (`.avi`, `.mkv`, `.rmvb`, `.wmv`, `.flv`, `.ts`).
  - Interface de player estilo YouTube em tela cheia (auto-ocultamento de controles após inatividade), velocidade (`0.5x` a `2.0x`), atalhos de teclado/TV (`Espaço`/`K`, `←`/`→`, `F`, `M`, `Esc`), legendas locais (`.vtt` e conversão em memória de `.srt`), seleção de faixa de áudio quando suportada pelo navegador, retomada de reprodução por usuário/dispositivo e próximo vídeo automático com contagem regressiva cancelável.
- **Princípios inegociáveis preservados**:
  - Operação **estritamente somente-leitura** sobre todas as pastas de mídia configuradas.
  - Respeito integral às permissões nativas do Nextcloud, autenticação, proteção CSRF e bloqueio de *path traversal*.
  - Funcionamento **100% offline / zero CDNs externos**, totalmente compatível com a `Content-Security-Policy` do Nextcloud e licenciado sob GNU AGPL v3.0.

### Out of Scope (Non-Goals)

- Edição, renomeação, upload, exclusão ou movimentação de arquivos de vídeo pela interface do plugin.
- Download automático de metadados, capas ou sinopses em serviços externos na internet (TMDB/OMDB).
- Compartilhamento público anônimo por link fora de sessões autenticadas do Nextcloud na v1.
- Migração para framework Vue/Webpack pesado ou microserviço externo via AppAPI na v1.

## Capabilities

### New Capabilities

- `folder-configuration`: Gerenciamento híbrido de pastas selecionadas (pastas pré-configuradas globalmente pelo Admin e pastas pessoais selecionadas por cada usuário), com seletor visual, validação de permissões nativas do Nextcloud e persistência fora das pastas de mídia.
- `video-catalog`: Navegação hierárquica sobre as pastas configuradas, filtragem de arquivos de vídeo, *breadcrumbs*, busca textual em toda a biblioteca ativa, ordenação e exibição de miniaturas em modo estritamente somente-leitura.
- `video-streaming-player`: Streaming progressivo via HTTP Range (`206 Partial Content`), inspeção e transcodificação opcional em memória via `ffmpeg`, e player HTML5 responsivo com tela cheia estilo YouTube, legendas locais `.vtt`/`.srt`, faixas de áudio, retomada de progresso e próximo vídeo automático.

### Modified Capabilities

<!-- Nenhuma capacidade existente está sendo modificada (projeto greenfield). -->

## Impact

- **Metadados e Bootstrap do App Nextcloud**: Criação de `appinfo/info.xml`, `appinfo/routes.php` e `lib/AppInfo/Application.php` para Nextcloud 30–32 (`nextcloud_video_player`).
- **Backend PHP (`lib/`)**: Novos controllers (`PageController`, `ConfigController`, `CatalogController`, `StreamController`, `SettingsController`) e serviços (`FolderConfigService`, `SafeNodeResolver`, `VideoCatalogService`, `SubtitleService`, `MediaStreamService`, `FfmpegService`) consumindo `OCP\Files\IRootFolder`, `OCP\IConfig`, `OCP\IUserSession` e `OCP\AppFramework`.
- **Frontend Vanilla JS/CSS (`js/`, `css/`, `templates/`)**: Interface principal do catálogo/player, modal de seleção de pastas, página de configurações de Admin e estilos integrados às variáveis de tema claro/escuro do Nextcloud.
- **Testes e Qualidade (`tests/`, `composer.json`, `package.json`)**: Suítes PHPUnit (cobertura Clover) e Vitest (cobertura LCOV), além de PHPStan e ESLint integrados ao workflow `.github/workflows/ci.yml` e ao SonarQube Quality Gate.
