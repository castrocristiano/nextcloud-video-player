# 01 — Arquitetura e Segurança

## Visão Geral

O **nextcloud-video-player** (`appId: folder_video_player`) adapta a experiência do `lan-video-player` para dentro do ecossistema **Nextcloud 30–32 (PHP 8.2+)**:

- **Backend PHP (`lib/`)**:
  - Utiliza exclusivamente `OCP\Files\IRootFolder` para acessar a árvore virtual do usuário autenticado, respeitando nativamente compartilhamentos, quotas, ACLs e montagens de *External Storage*.
  - Armazena configurações de pastas no `OCP\IConfig` (escopo global de Admin + escopo individual por usuário).
  - Realiza streaming eficiente com suporte a HTTP `Range` (`206 Partial Content`) e suporte opcional a `ffmpeg` para miniaturas e transcodificação sob demanda.
- **Frontend Vanilla JS + CSS (`js/`, `css/`, `templates/`)**:
  - Porta os recursos comprovados do `lan-video-player` (player estilo YouTube em tela cheia, atalhos de teclado/TV, velocidade, legendas `.vtt`/`.srt`, retomada de progresso, próximo vídeo automático, busca e ordenação).
  - Usa variáveis CSS nativas do Nextcloud para suporte automático aos temas claro e escuro, sem dependências de CDN externas.

## Garantias de Segurança

1. **Somente-Leitura Estrito**: Nenhum arquivo ou pasta dentro dos diretórios de vídeo é criado, renomeado, movido ou removido.
2. **Isolamento de Caminhos**: Todo acesso é restrito às pastas selecionadas configuradas e validado contra *path traversal* e permissões nativas do usuário no Nextcloud.
3. **100% Offline & CSP**: Todos os assets são servidos localmente pelo app Nextcloud, respeitando `Content-Security-Policy` estrita.
