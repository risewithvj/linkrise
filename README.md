# LinkRise Enterprise v4.0

Enterprise WordPress URL shortener with public API, admin API, analytics, and SaaS-ready architecture.

## Build
- `npm install`
- `npm run build`

## Public Endpoints
- `POST /wp-json/linkrise/v1/public/shorten`
- `POST /wp-json/linkrise/v1/public/bulk-shorten`
- `POST /wp-json/linkrise/v1/public/report`
- `GET /wp-json/linkrise/v1/public/stats/{shortcode}`

## Security
- Rate limit on public endpoints
- IP hash-only report storage
- Admin endpoints require `manage_options` + WP REST nonce
