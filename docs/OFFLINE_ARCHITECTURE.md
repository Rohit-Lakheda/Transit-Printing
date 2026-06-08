# Offline-First Architecture

## Flow

```
Operator UI → IndexedDB (local) → Sync Engine (background) → Laravel Sync API → MySQL (cloud)
                     ↑
              Local Scan Validator (offline rules)
```

## Client (Browser)

- **DB:** `event_offline_db` (IndexedDB v2)
- **Stores:** attendees, locations, categories, blocked_regids, master_badges, bypassed_regids, scan_logs_local, sync_queue, failed_sync_logs, sync_meta
- **Modules:** `public/js/offline/*.js`
- **Service worker:** `public/operator-sw.js`

## Server

- **Config:** `config/offline.php`
- **Services:** `app/Services/Offline/*`
- **API:** `routes/web.php` → `operator/offline/*`
- **Idempotency:** `client_scan_id`, `client_print_id` unique per `event_id`

## Pre-Event

1. Open **Select Location** page
2. Choose event → **Download Event Data Locally**
3. Select location → scan

## Sync

- Health: `GET /operator/offline/health`
- Bootstrap: `GET /operator/offline/bootstrap?event_id=`
- Push scans: `POST /operator/offline/push-scans`
- Background interval: 20s (configurable)

## LAN Mode

Set `OFFLINE_LAN_BASE_URL=http://192.168.x.x` in `.env`. Clients ping LAN health first; if cloud is down, sync uses LAN server.

## Phase 2 — Bidirectional sync + offline printing

### Local → Cloud (push)
- Pending scans in `sync_queue` → `POST /operator/offline/push-scans`
- Pending prints in `sync_queue` → `POST /operator/offline/push-prints`
- Idempotent via `client_scan_id` / `client_print_id`

### Cloud → Local (pull)
- Every sync cycle: `GET /operator/offline/pull?event_id=&since=`
- Merges: attendees, printing_logs → `print_index`, scanning_logs → `scan_logs_remote`, categories
- LAN feed: `GET /operator/offline/pull-location-scans` for cross-device duplicate awareness

### Offline printing
- Download bootstrap (includes badge layouts + print index)
- Scan & Print page uses local validation + local print log + sync queue
- Online: fetches `/operator/offline/print-payload` for full QR render
- Offline: renders from cached layout (QR optional if not cached)
