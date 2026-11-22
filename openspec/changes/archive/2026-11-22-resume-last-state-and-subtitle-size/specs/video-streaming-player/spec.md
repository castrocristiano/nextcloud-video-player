# Spec Delta — video-streaming-player

## ADDED Requirements

### Requirement: Adjustable subtitle font size control
The system SHALL provide dedicated controls in the player toolbar to decrease and increase subtitle font size within safe readability bounds, applying the selected scale immediately to the subtitle overlay in both windowed and fullscreen modes and persisting it across sessions.

#### Scenario: Increasing and decreasing subtitle font size during playback
- **GIVEN** o usuário está assistindo a um vídeo com legenda local ativa no player (em janela ou em tela cheia)
- **WHEN** o usuário clica no botão de aumentar tamanho da fonte (`A+`) ou diminuir (`A-`)
- **THEN** o tamanho do texto exibido no overlay de legendas é atualizado imediatamente, o indicador percentual na barra exibe o novo valor (entre `75%` e `200%`, em passos de `15%` ou `25%`) e a escolha é salva nas preferências do usuário

#### Scenario: Clamping subtitle font size at minimum and maximum bounds
- **GIVEN** o tamanho da legenda já está no limite mínimo (`75%`) ou máximo (`200%`)
- **WHEN** o usuário clica repetidamente em diminuir (`A-`) ou aumentar (`A+`)
- **THEN** o valor permanece limitado ao intervalo permitido (`75%`–`200%`) sem quebrar o layout do player nem ocultar o vídeo

#### Scenario: Subtitle font size restored across sessions and devices
- **GIVEN** o usuário configurou o tamanho das legendas para `150%` em uma sessão anterior
- **WHEN** o mesmo usuário abre qualquer vídeo com legenda em uma nova sessão ou em outro dispositivo autenticado
- **THEN** o player aplica automaticamente a escala `150%` ao overlay de legendas desde a primeira fala exibida
