# Changelog

All notable changes to **`nextcloud_video_player`** are documented in this file.

## [0.1.0] - 2026-11-21

### Added
- Hybrid video folder configuration: global pre-configured folders managed by Nextcloud administrators and personal folders selected by each user via visual folder picker.
- Dedicated relational database tables (`ncvp_folders`, `ncvp_playback_progress`, `ncvp_user_preferences`, `ncvp_playback_sessions`) managed by Nextcloud migrations (PostgreSQL, MariaDB/MySQL, SQLite).
- Playback session lifecycle management with periodic heartbeats, cross-device progress synchronization, configurable concurrent session limits, automatic idle expiration, Nextcloud logout revocation, and non-blocking PHP session release (`OCP\ISession::close()`) during media streaming.
- Strictly read-only hierarchical video catalog with breadcrumbs, case-insensitive library-wide search, sorting, and read-only thumbnails.
- Authenticated HTTP `Range` (`206 Partial Content`) video streaming, optional in-memory FFmpeg transcoding for legacy formats, local subtitles (`.vtt` and in-memory `.srt` to WebVTT conversion), audio track selector, playback speed (`0.5x`–`2.0x`), YouTube-style fullscreen controls, and autoplay next video countdown.
- Native multi-language localization (`OCP\IL10N` and `OC.L10N`) with English (`en`) as default/fallback and full Brazilian Portuguese (`pt_BR`) translation.
