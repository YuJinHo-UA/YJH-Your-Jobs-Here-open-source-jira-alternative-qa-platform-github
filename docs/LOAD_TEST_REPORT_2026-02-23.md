# Load Test Report (50 inserts)

## Test date
- 2026-02-23

## Scope
- Target: `POST /api/bugs.php`
- Goal: create 50 bug records and measure response times
- Environment: local server `http://127.0.0.1:8000`
- Auth user: `admin@yujin.ho`

## Method
1. Login through `login.php` and store session cookie.
2. Send 50 sequential `POST` requests to `api/bugs.php`.
3. Payload fields:
   - `project_id=1`
   - `title=Load50 API bug #N`
   - `description=bulk 50 insert ...`
   - `severity=major`
   - `priority=medium`
   - `status=new`
4. Collect per-request latency and API response body (`status`, `id`).
5. Validate created IDs are unique and continuous.

## Results
- Total requests: `50`
- Success (`status=created`): `50`
- Failed: `0`
- First created ID: `174`
- Last created ID: `223`
- Unique created IDs: `50`

### Latency
- Min: `17.60 ms`
- Avg: `18.72 ms`
- P95: `20.16 ms`
- Max: `21.24 ms`

## Evidence snapshot
- API returned created IDs in the range `174..223`.
- Recent list endpoint (`GET /api/bugs.php`) contained inserted records (example titles: `Load50 API bug #27`, `#28`, `#29`).

## Conclusion
- Insert path handled load of 50 create operations successfully.
- Error rate: `0%`.
- Response-time profile is stable (low spread between avg/p95/max).
- Under this load level, no performance bottlenecks were observed for bug creation API.
