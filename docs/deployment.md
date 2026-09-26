# Putting the demo online for free

## Recommendation

**Use a Cloudflare Tunnel from the production stack you already run.**

The stack at `~/docker/production-booking-saas` already deploys itself from `main` through `deploy.yml`. A tunnel is the only extra piece needed to give it a public HTTPS address:

- **Cost:** hosting is $0. You only pay for a domain, roughly €10 a year.
- **No exposure of your home network:** no router port forwarding and no public IP.
- **HTTPS is automatic,** so the link works on any phone.
- **Same app everywhere:** the portfolio link runs exactly what you test locally.

The stack is already set up for it. `docker-compose.yml` has an opt-in `cloudflared` service. nginx has a dedicated tunnel port (8080) that takes the real visitor IP from Cloudflare's `CF-Connecting-IP` header, so per-IP OTP limits stay per visitor.

Without that tunnel port, every visitor would appear to come from the tunnel's single IP. The whole site would then share one rate-limit bucket: 20 codes a day in total.

**Use option B (Oracle Cloud) instead** if the site must stay up when your home server or internet connection is down.

### Why not Render, Railway or Fly.io

These were the free-tier terms when this guide was written; they change often, so check each pricing page.

| Provider | Free tier | Problem for this app |
|---|---|---|
| Render | Free web services sleep after about 15 minutes idle. | A recruiter's first click waits about a minute. Free plans don't run background workers or cron, and this app needs both: the queue sends SMS and e-mail, and the scheduler sends reminders. There's no MySQL, the free Postgres expires, and uploads are lost on every deploy because the disk isn't persistent. |
| Railway | No permanent free tier: trial credit, then a paid plan. | Not free. |
| Fly.io | No free allowance for new accounts; pay as you go. | A few dollars a month for a VM, a volume and a database. |

All three would also mean re-architecting the app into single containers with a managed database, instead of the Compose stack you already test with.

---

## Option A: Cloudflare Tunnel from the home server

**What you need:**
- A free Cloudflare account.
- A domain whose DNS is managed by Cloudflare. Cloudflare Registrar sells domains at cost, or you can move an existing domain's nameservers.

### 1. Create the tunnel

1. Go to Cloudflare dashboard → **Zero Trust → Networks → Tunnels → Create a tunnel** → **Cloudflared**.
2. Name it `booking-prod`.
3. On the install screen, copy only the token: the long string after `--token`. Don't run the install command; Docker Compose runs `cloudflared` for you.
4. Add a **public hostname**:
   - Subdomain `booking`, on your domain.
   - **Service:** `HTTP`, URL `webserver:8080`.

   Use exactly `webserver:8080`, not `:80` or `localhost`. Port 8080 is the tunnel entry point that trusts Cloudflare's client IP. It is not published on the host, so only this stack's containers can reach it.

### 2. Configure the prod checkout

Both files below live only on the server; they're gitignored and never committed.

`~/docker/production-booking-saas/.env` (root, read by Docker Compose):

```dotenv
COMPOSE_PROJECT_NAME=booking-prod
COMPOSE_PROFILES=tunnel
CLOUDFLARE_TUNNEL_TOKEN=<token from step 1>
```

`~/docker/production-booking-saas/src/.env` (Laravel):

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://booking.example.com
ASSET_URL=https://booking.example.com
SESSION_SECURE_COOKIE=true
LOG_LEVEL=warning
```

Then lock both files down:

```bash
chmod 600 ~/docker/production-booking-saas/.env ~/docker/production-booking-saas/src/.env
```

### 3. Start it

Merge anything to `main`: the deploy runs `docker compose up -d`, which reads `COMPOSE_PROFILES` from `.env` and starts `cloudflared`.

To start it by hand instead:

```bash
cd ~/docker/production-booking-saas
docker compose up -d cloudflared
docker compose exec app php artisan optimize   # config is cached; pick up the new APP_URL
docker compose logs cloudflared | grep -i registered   # expect "Registered tunnel connection" lines
curl -sI https://booking.example.com/book | head -1   # HTTP/2 200
```

### 4. Before you share the link

- **Seeded admin passwords are public knowledge.** The seeder creates `superadmin@example.com` and the shop admins with the password `password`.
  - On a public URL, anyone can sign in as super admin.
  - Change every password before sharing the link (`docker compose exec app php artisan tinker`, then `User::where('email', …)->first()->update(['password' => Hash::make('…')])`).
  - Or put `/admin` behind **Cloudflare Access**: Zero Trust → Access → Applications → Self-hosted, path `admin*`, e-mail one-time PIN. It's free for up to 50 users.
  - For recruiters, give out a separate shop-admin account rather than the super admin.
- **SMS codes:** with `SMS_DRIVER=log`, visitors never receive their code, so they can't finish a booking.
  - Twilio trial accounts only text numbers you have verified. That's enough for demoing on your own phone, but not for strangers.
  - For a public portfolio demo, a clearly labelled "demo mode" that shows the code on screen is the practical fix. It's not built yet.
- **E-mail:** prod Mailpit keeps confirmations on the server, which is fine for a demo. For real delivery, set the `MAIL_*` variables to an SMTP provider's free tier.
- **Cloudflare settings:**
  - Turn on SSL/TLS → **Always Use HTTPS**.
  - Optionally add a free WAF rate-limiting rule for `/api/*` as an extra layer.
- **Services that must stay private:** Mailpit, MySQL and Adminer are not on the tunnel. Never add public hostnames for them.

### 5. Demo content

- **Prod database already has the three demo shops:**
  ```bash
  docker compose exec app php artisan db:seed --class=DemoContentSeeder --force
  ```
  This adds heroes, galleries and FAQs only where a shop has none.
- **Brand-new, empty database only:** `php artisan migrate --seed --force`.

### Quick demo without a domain

This gives you a temporary public link; it lasts only while the command runs:

```bash
docker run --rm --network booking-prod_booking-net cloudflare/cloudflared:2026.9.3 \
  tunnel --no-autoupdate --url http://webserver:8080
```

It prints a random `https://….trycloudflare.com` address. Assets and links follow `APP_URL`/`ASSET_URL`, so for the duration:
1. Set both to that address.
2. Run `php artisan optimize`.
3. Change them back afterwards.

Use this only as a stopgap: the URL changes every time.

### Trade-offs

- **Uptime depends on your home server:** power, ISP and reboots. A free uptime monitor such as UptimeRobot tells you when it's down.
- **Cloudflare sees the traffic** (it terminates TLS). That's normal for a public demo.

---

## Option B: Oracle Cloud Always Free VM

This runs the same Compose stack on a free, always-on ARM VM.

1. **Sign up** at cloud.oracle.com. A card is needed to verify your identity; Always Free resources aren't charged. Choose a home region; you can't change it later.
2. **Create the instance:**
   - Compute → Instances → Create.
   - Image: **Ubuntu 24.04**.
   - Shape: **VM.Standard.A1.Flex**, 2 OCPU and 12 GB. The free allowance is 4 OCPU and 24 GB in total.
   - Paste your SSH public key.

   If you get "Out of host capacity", try another availability domain, or retry later.
3. **Firewall:** leave only SSH (22) open in the VCN security list. With a tunnel, nothing else needs to be public.
4. **Install Docker:**
   ```bash
   curl -fsSL https://get.docker.com | sh
   sudo usermod -aG docker $USER   # log out and back in
   docker network create nginx-net   # the compose file expects it
   ```
5. **Bootstrap the stack:** follow the README's "One-time prod bootstrap" section. Every image used here has ARM64 builds: `php:8.4-fpm`, `mysql:8.0`, `redis`, `nginx`, `mailpit`, `cloudflared`.
6. **Set up the tunnel:** same as option A, steps 1–5.
7. **Continuous deployment:**
   - Install a GitHub self-hosted runner on the VM: repo → Settings → Actions → Runners → New, Linux ARM64. Install it as a service.
   - Set `DEPLOY_PATH` in `deploy.yml` to the checkout path on the VM.
   - Only one runner should serve `deploy.yml`, so remove the home-server runner or target the VM's runner with a label.

**Caveats:**
- Oracle may reclaim Always Free instances that sit almost idle for a week. Upgrading the account to Pay As You Go stops that and still bills nothing within the free limits.
- Back up the MySQL volume yourself: `docker compose exec mysql mysqldump …`.

---

## Handling secrets

- **Server only:** secrets live in the two `.env` files on the server, gitignored and `chmod 600`. They are never committed or put in GitHub Actions: the self-hosted runner reads them in place, so they never appear in workflow logs.
- **Generate strong values:**
  - DB passwords: `openssl rand -base64 32`.
  - `APP_KEY`: `php artisan key:generate --force`. The deploy refuses to run with an empty key.
- **Tunnel token:** treat it like a password. If it leaks, rotate it in the tunnel's settings and update `CLOUDFLARE_TUNNEL_TOKEN`.
- **Debug tools stay local:** Adminer (the `debug` profile) is bound to `127.0.0.1` and is never started by the deploy. To use it on a remote server, go through SSH:
  ```bash
  ssh -L 8083:127.0.0.1:8083 you@server
  ```
  Set `ADMINER_PORT=8083` in the prod root `.env` so it doesn't clash with dev.
