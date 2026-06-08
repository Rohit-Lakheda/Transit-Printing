# Phase 4 Offline Hub (Single Event)

This project now supports a venue-first offline mode where operator devices can keep working even when internet is unstable/unavailable.

## 1) Venue Setup (brief)

1. Keep one laptop/mini-PC as the **offline hub** on venue WiFi.
2. Run app on hub:
   - `php artisan serve --host=0.0.0.0 --port=8080`
3. Set `.env` on hub:
   - `OFFLINE_LAN_BASE_URL=http://<hub-lan-ip>:8080`
   - `OFFLINE_MODE=lan_first`
   - `OFFLINE_PREFER_LAN=true`
   - (optional) `OFFLINE_SYNC_TOKEN=<secret>`
4. On each operator device:
   - Open `http://<hub-lan-ip>:8080/operator/home`
   - Go to scanning location page and download bootstrap data once.

## 2) Recommended `.env` keys

```env
OFFLINE_LAN_BASE_URL=http://192.168.1.50:8080
OFFLINE_MODE=lan_first
OFFLINE_PREFER_LAN=true
OFFLINE_SYNC_INTERVAL=20
OFFLINE_MAX_SYNC_RETRIES=8
OFFLINE_SYNC_TOKEN=
```

Modes:
- `cloud_fallback_lan` (default): cloud first, LAN fallback.
- `lan_first`: LAN first (best for venue hub).

## 3) Office Testing Matrix

Use 2 devices (or 2 browsers) on same WiFi.

### Scenario A - Normal internet
- Keep internet ON.
- Register attendee on device A.
- Scan/print on device B.
- Expected: near-real-time sync and success.

### Scenario B - Internet OFF, WiFi ON (hub reachable)
- Disable internet on router uplink OR disconnect WAN.
- Keep local WiFi/LAN alive.
- Register on A, scan on B.
- Expected: still works; data syncs through hub over LAN.

### Scenario C - Device network flap
- On device A toggle WiFi OFF for 30-60 seconds, then ON.
- Perform registration while disconnected.
- Expected: local queue stores data; auto-sync after reconnect.

### Scenario D - Hub unreachable
- Stop hub server for 1-2 minutes.
- Try scanning on devices already bootstrapped.
- Expected: local validation works; pending queue increases.
- Start hub again; expected queue drains automatically.

### Scenario E - Fresh device without bootstrap
- New device, internet OFF.
- Try scan/search/print.
- Expected: prompt/failure until bootstrap data downloaded.

## 4) Quick validation commands (from hub)

```bash
curl http://127.0.0.1:8080/operator/offline/health
curl "http://127.0.0.1:8080/operator/offline/bootstrap?event_id=1"
```

## 5) Event-day checklist

- [ ] Hub server running on `0.0.0.0:8080`
- [ ] All devices on same WiFi
- [ ] Bootstrap downloaded on each device
- [ ] Offline status visible on operator home
- [ ] One registration + one scan + one print smoke test done
- [ ] Pending sync count returns to 0 when network stable
