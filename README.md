# Booking SaaS API & Web App

## Environments

| | Path | Compose project | Web |
|---|---|---|---|
| Dev | `~/docker/booking-saas` | `booking-saas` (`booking-*` containers) | :8090 |
| Prod | `~/docker/production-booking-saas` | `booking-prod` (`booking-prod-*` containers) | :8091 |

Pushes to `main` run the CI tests (GitHub-hosted), then `.github/workflows/deploy.yml`
deploys the tested commit to the **prod** path only. The dev checkout, its containers
and its MySQL volume are never touched by CI.

### One-time prod bootstrap

```bash
git clone https://github.com/markosavicle/booking-saas.git ~/docker/production-booking-saas
cd ~/docker/production-booking-saas
cp .env.example .env         # CONTAINER_PREFIX=booking-prod, WEB_PORT=8091, MAILPIT_PORT=8026, fresh DB passwords
cp src/.env.example src/.env # APP_ENV=production, APP_DEBUG=false, DB_HOST=mysql, matching DB creds
COMPOSE_PROJECT_NAME=booking-prod docker compose up -d --build
COMPOSE_PROJECT_NAME=booking-prod docker compose exec app sh -c 'composer install --no-dev -o && php artisan key:generate --force'
```

Then re-run the "Deploy Booking SaaS" workflow (Actions → Run workflow) and point the
reverse proxy at `booking-prod-web:80`.
