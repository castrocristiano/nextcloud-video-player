# nextcloud-video-player (`folder_video_player`)

Aplicativo (plugin) para **Nextcloud 30–32 (PHP 8.2+)** que traz a experiência de biblioteca e player de vídeo do [lan-video-player](https://github.com/castrocristiano/lan-video-player) diretamente para o Nextcloud, reproduzindo vídeos organizados em **pastas selecionadas já configuradas** (modelo híbrido: pastas globais configuradas pelo administrador + pastas pessoais escolhidas pelo usuário), com **persistência relacional em tabelas**, **controle formal de sessão**, **internacionalização multi-idioma (`en` padrão + `pt_BR`)** e pronto para o **catálogo oficial da Nextcloud App Store (`apps.nextcloud.com`)**.

> **Documentação completa**: consulte a pasta [wiki/](wiki/Home.md) ou a Wiki do repositório no GitHub.
> **Especificações (SDD)**: este projeto utiliza **OpenSpec** em [openspec/](openspec/config.yaml).

---

## Principais Funcionalidades

- **Configuração Híbrida de Pastas Selecionadas (`ncvp_folders`)**:
  - **Admin**: pré-configura pastas globais/compartilhadas em *Configurações de Administração → Player de Vídeo* (incluindo montagens de *External Storage*), respeitando rigorosamente as permissões nativas de leitura de cada usuário.
  - **Usuário**: seleciona e gerencia pastas adicionais da sua própria conta pelo seletor visual de pastas no app.
- **Tabelas Relacionais Dedicadas & Controle de Sessão (`ncvp_*`)**:
  - Migração oficial do Nextcloud (`Version0001Date20261121000000`) compatível com **PostgreSQL**, **MariaDB/MySQL** e **SQLite** criando 4 tabelas: `ncvp_folders`, `ncvp_playback_progress`, `ncvp_user_preferences` e `ncvp_playback_sessions`.
  - Controle de sessão com token criptográfico CSPRNG, *heartbeat* periódico (persistindo progresso entre dispositivos), encerramento via `keepalive`/`sendBeacon`, expiração automática (`SessionCleanupJob`), revogação no logout (`UserLoggedOutListener`), limite configurável de sessões simultâneas (`429 Too Many Requests`) e liberação do *lock* da sessão PHP (`OCP\ISession::close()`) durante o streaming.
- **Somente-Leitura Estrito & Segurança Nativa Nextcloud**:
  - Usa exclusivamente `OCP\Files\IRootFolder`, nunca gravando nem modificando arquivos nas pastas de mídia.
  - 100% offline/auto-hospedado sem CDNs externos (compatível com a `Content-Security-Policy` estrita do Nextcloud).
- **Streaming HTTP Range (`206 Partial Content`) & Player Estilo YouTube**:
  - Streaming progressivo com *seek* instantâneo e suporte opcional a transcodificação em memória via `ffmpeg`.
  - Tela cheia estilo YouTube com auto-ocultamento de controles após 2,5s, velocidade (`0.5x`–`2.0x`), atalhos de teclado/TV (`Espaço`/`K`, `←`/`→`, `F`, `M`, `Esc`), retomada sincronizada entre dispositivos, próximo vídeo automático com contagem regressiva de 5s, legendas locais (`.vtt` e conversão em memória de `.srt`) e seletor de faixa de áudio.
- **Internacionalização Multi-Idioma (i18n)**:
  - Baseada em `OCP\IL10N` e `OC.L10N` (`l10n/`), com **Inglês (`en`) como idioma padrão/fallback** e tradução 100% completa em **Português do Brasil (`pt_BR`)**.

---

## 1. Instalação no Nextcloud

### Opção A — Via Catálogo Oficial (Nextcloud App Store)
1. No painel do Nextcloud como Administrador, acesse **Aplicativos (Apps) → Multimídia (Multimedia)**.
2. Localize **Nextcloud Video Player** (`folder_video_player`) e clique em **Baixar e ativar**.

### Opção B — Via Pacote `.tar.gz` da GitHub Release (ou Manual)
```bash
# Extrair na pasta custom_apps (ou apps) da instalação Nextcloud:
tar -xzf folder_video_player-v0.1.0.tar.gz -C /var/www/html/custom_apps/

# Ativar o aplicativo (executa automaticamente as migrações das tabelas ncvp_*):
php occ app:enable folder_video_player
```

---

## 2. Publicação no Catálogo da Nextcloud App Store (GitHub Secrets)

O job `release-on-main` em [.github/workflows/ci.yml](.github/workflows/ci.yml) automatiza a assinatura de integridade (`appinfo/signature.json` + SHA-512 do `.tar.gz`) e o envio para `https://apps.nextcloud.com/api/v1/apps/releases`:

1. Gere a chave privada e o CSR do aplicativo:
   ```bash
   openssl req -new -newkey rsa:4096 -nodes \
     -keyout folder_video_player.key \
     -out folder_video_player.csr \
     -subj "/CN=folder_video_player"
   ```
2. Acesse `https://apps.nextcloud.com/developer/apps/register`, envie o conteúdo de `folder_video_player.csr` para baixar o certificado `folder_video_player.crt` e gere seu token em *My Account → API-Token*.
3. Configure os 3 **Secrets** no repositório GitHub (*Settings → Secrets and variables → Actions*):
   - `NC_APP_PRIVATE_KEY`: conteúdo PEM de `folder_video_player.key`
   - `NC_APP_CERTIFICATE`: conteúdo PEM de `folder_video_player.crt`
   - `NC_APPSTORE_TOKEN`: token da API da Nextcloud App Store

---

## 3. Validação Local, Testes e SonarQube

```bash
# 1. Validar especificações OpenSpec em modo estrito
openspec validate --all --strict --no-interactive

# 2. Validar appinfo/info.xml contra o schema XSD oficial da App Store
curl -fsSL -o /tmp/info.xsd https://apps.nextcloud.com/schema/apps/info.xsd
xmllint --schema /tmp/info.xsd --noout appinfo/info.xml

# 3. Executar lint PHP, PHPStan e PHPUnit com cobertura Clover
composer run lint
vendor/bin/phpstan analyse
XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-clover build/logs/clover.xml

# 4. Executar ESLint e Vitest com cobertura LCOV
npm run lint
npm run test:coverage

# 5. Executar SonarQube Quality Gate (usando SONAR_HOST_URL e SONAR_TOKEN do ambiente ou .env)
cp .env.example .env
./sonar_check.sh
```

---

## Licença

Este projeto é software livre licenciado sob a **GNU Affero General Public License v3.0 (GNU AGPL v3.0)**. Consulte o arquivo [LICENSE](LICENSE).
