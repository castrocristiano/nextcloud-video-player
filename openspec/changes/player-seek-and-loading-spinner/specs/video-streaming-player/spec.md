# Spec Delta — video-streaming-player

## ADDED Requirements

### Requirement: Player loading spinner during buffering and seek
The system SHALL display an accessible, offline CSS loading spinner overlay inside the video stage whenever a video is opening, buffering, or seeking, and hide it immediately once playback resumes or an error occurs.

#### Scenario: Showing and hiding the spinner during video startup and buffering
- **GIVEN** o usuário clica em um vídeo no catálogo para iniciar a reprodução
- **WHEN** o elemento de vídeo entra nos estados `loadstart`, `waiting` ou `seeking`
- **THEN** o palco do vídeo exibe o spinner centralizado sobre a área do player (tanto em janela quanto em tela cheia), ocultando-o automaticamente assim que o navegador dispara `canplay`, `playing`, `seeked` ou `error`

### Requirement: Responsive seek, fast-forward, and rewind controls
The system SHALL update the displayed playback timestamp immediately on forward/rewind/seek interactions, debounce rapid consecutive seek steps on transcoded streams, and fast-seek the backend stream without decoding all preceding frames from timestamp zero whenever supported.

#### Scenario: Rapid consecutive forward or rewind clicks on a transcoded video
- **GIVEN** o usuário está assistindo a um vídeo em modo transcodificado (`FFmpeg`) e clica rapidamente três vezes em `+10s` (ou pressiona `→` três vezes)
- **WHEN** os cliques ocorrem dentro da janela de debounce
- **THEN** a barra de progresso e o tempo exibido avançam imediatamente `+30s`, o spinner de carregamento é exibido e o player dispara apenas uma nova requisição de stream já posicionada no novo instante acumulado

#### Scenario: Seeking backward or forward in a direct-stream video
- **GIVEN** o usuário está assistindo a um vídeo compatível em streaming direto (`.mp4` ou `.webm` via HTTP Range)
- **WHEN** o usuário clica em `-10s`, `+10s` ou arrasta a barra de progresso
- **THEN** o player atualiza `currentTime` diretamente no elemento HTML5 sem reiniciar a sessão de streaming nem perder a trilha de áudio/legenda selecionada
