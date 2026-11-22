# video-streaming-player Specification

## Purpose

Entrega streaming progressivo via HTTP Range, transcodificação opcional em memória para formatos legados e uma interface de player HTML5 completa e 100% offline integrada ao Nextcloud.

## Requirements

### Requirement: Authenticated HTTP Range video streaming
The system SHALL stream video files belonging to configured accessible folders to authenticated Nextcloud users with an authorized playback session, providing full HTTP `Range` (`206 Partial Content`) support without holding the PHP session lock during transfer.

#### Scenario: Seeking inside a browser-compatible MP4 video
- **GIVEN** o usuário autenticado iniciou uma sessão de reprodução para um arquivo `.mp4` dentro de uma pasta configurada
- **WHEN** o player envia uma requisição com cabeçalho `Range: bytes=1048576-2097151` ao saltar na barra de progresso
- **THEN** o servidor valida a sessão, libera o lock da sessão PHP e responde com status `206 Partial Content`, cabeçalhos `Content-Range`, `Accept-Ranges: bytes` e `Content-Length` exatos, entregando apenas o intervalo solicitado

#### Scenario: Unauthenticated, unauthorized, or revoked session streaming attempt
- **GIVEN** uma requisição tenta acessar o endpoint de streaming sem sessão autenticada válida no Nextcloud, com sessão de reprodução revogada/expirada ou apontando para um arquivo fora das pastas configuradas
- **WHEN** o servidor processa a requisição
- **THEN** o acesso é recusado (`401 Unauthorized` ou `403 Forbidden`) e nenhum byte do arquivo é transmitido

### Requirement: Optional in-memory FFmpeg transcoding for legacy formats
The system SHALL support optional real-time in-memory transcoding via `ffmpeg` (when explicitly enabled by the administrator and available on the host) to play legacy formats (such as `.avi`, `.rmvb`, `.wmv`, `.flv`, `.ts`, or incompatible `.mkv`) with duration probing and timestamp seeking without writing temporary video files inside media folders.

#### Scenario: Fallback to transcoded stream when enabled
- **GIVEN** o administrador habilitou transcodificação via `ffmpeg` e o usuário abre um vídeo `.avi` não suportado nativamente pelo navegador
- **WHEN** o player detecta incompatibilidade nativa ou formato legado
- **THEN** o sistema consulta a duração do vídeo e inicia streaming MP4 fragmentado (H.264/AAC) em memória a partir do instante solicitado, mantendo a barra de *seek* funcional

#### Scenario: Unsupported format when transcoding is disabled or unavailable
- **GIVEN** a transcodificação via `ffmpeg` está desabilitada ou indisponível e o usuário abre um vídeo incompatível com o navegador
- **WHEN** a reprodução falha por erro de mídia/codec
- **THEN** o player exibe uma mensagem amigável e recuperável explicando a incompatibilidade de formato e permitindo voltar ao catálogo sem travar a interface

### Requirement: YouTube-style fullscreen player and playback controls
The system SHALL provide a responsive HTML5 video player integrated with Nextcloud light/dark themes featuring play/pause, seek bar with current/total time, skip ±10s, volume/mute, playback speed (`0.5x` to `2.0x`), keyboard/TV shortcuts, and YouTube-style fullscreen mode where controls auto-hide after inactivity.

#### Scenario: Fullscreen mode auto-hides controls on inactivity
- **GIVEN** um vídeo está em reprodução em modo tela cheia
- **WHEN** o usuário permanece sem mover o mouse ou interagir por 2,5 segundos
- **THEN** o vídeo ocupa toda a área útil (`100vw × 100vh`) e a barra de controles oculta-se suavemente, reaparecendo imediatamente ao mover o ponteiro ou pressionar uma tecla de controle

#### Scenario: Adjusting playback speed and keyboard shortcuts
- **GIVEN** um vídeo está aberto no player
- **WHEN** o usuário altera a velocidade para `1.5x` e pressiona `Espaço` (ou `K`), `←`/`→`, `M` e `F`
- **THEN** a velocidade de áudio e vídeo é aplicada e persistida na tabela de preferências do usuário, e os atalhos controlam respectivamente reproduzir/pausar, retroceder/avançar 10s, silenciar e alternar tela cheia

### Requirement: Local subtitles and audio track selection
The system SHALL detect compatible local subtitle files (`.vtt` and `.srt`) stored in the same folder with matching base filename, convert `.srt` to WebVTT in memory without modifying files, and expose both subtitle selection and browser-supported audio track selection in the player controls.

#### Scenario: Loading matching SRT and VTT local subtitles
- **GIVEN** um vídeo `Episodio01.mp4` possui na mesma pasta os arquivos `Episodio01.pt-BR.srt` e `Episodio01.en.vtt`
- **WHEN** o usuário abre `Episodio01.mp4` no player
- **THEN** ambas as faixas aparecem no seletor de legendas com rótulos legíveis (`pt-BR`, `en`), podendo ser ativadas, alternadas ou desativadas sem criar arquivos `.vtt` no armazenamento do usuário

#### Scenario: Audio track selector fallback
- **GIVEN** um vídeo possui apenas uma faixa de áudio ou o navegador do cliente não expõe `audioTracks`
- **WHEN** o vídeo é carregado no player
- **THEN** o controle de faixas de áudio exibe o estado padrão sem quebrar a reprodução

### Requirement: Server-persisted playback progress resume and autoplay next video
The system SHALL persist per-user playback progress in the application's database table via playback session heartbeats outside the media folders, indicate partial progress in the catalog, resume playback across devices with a restart option, reset progress once a video exceeds 95% completion, and offer manual and automatic next-video transition with a cancellable countdown.

#### Scenario: Resuming a partially watched video across devices
- **GIVEN** o usuário assistiu 15 minutos de um vídeo de 40 minutos e fechou o player
- **WHEN** o usuário retorna ao catálogo (no mesmo dispositivo ou em outro autenticado na mesma conta Nextcloud) e abre novamente o mesmo vídeo
- **THEN** o cartão do catálogo exibe barra de progresso parcial e o player retoma a reprodução a partir do ponto salvo na tabela do servidor exibindo um aviso discreto com botão para reiniciar do início

#### Scenario: Autoplay next video with cancellable countdown
- **GIVEN** a opção "Próximo vídeo automático" está ativada e existe um vídeo seguinte na mesma pasta
- **WHEN** o vídeo atual termina
- **THEN** o progresso do vídeo concluído é marcado como finalizado na tabela do servidor, a sessão atual é encerrada, o player exibe uma contagem regressiva de 5 segundos com ações "Reproduzir Agora" e "Cancelar" e inicia o próximo vídeo automaticamente se não cancelado

### Requirement: Strict offline operation and Nextcloud CSP compliance
The system MUST serve all scripts, styles, icons, and templates exclusively from the local Nextcloud app bundle without external CDN requests or telemetry, complying with Nextcloud Content Security Policy (`default-src 'self'`).

#### Scenario: Loading the app in an air-gapped network
- **GIVEN** a instância Nextcloud opera em rede local isolada sem acesso à internet
- **WHEN** o usuário abre o aplicativo e reproduz um vídeo
- **THEN** 100% da interface, ícones, estilos e controles carregam sem erros de CSP nem requisições de rede para domínios externos
