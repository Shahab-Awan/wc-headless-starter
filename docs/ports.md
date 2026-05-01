# Port allocation - wc-headless-starter

Default local development ports.

| Port | Service | Scope | Conflicts with |
| --- | --- | --- | --- |
| 8099 | WordPress (Apache) | localhost | - |
| 3316 | MySQL 8.0 | 127.0.0.1 | - |
| 6382 | Redis 7 | container only | - |
| 5175 | Vite / SvelteKit dev | localhost | - |

## Why These

The defaults avoid the most common WordPress, MySQL, Redis, and Vite ports
used by other local projects. If they collide on your machine, change them
in the files listed below.

## If you need to change a port

1. Grep for the old port everywhere under this directory
2. Update `docker-compose.yml`, `spa/vite.config.ts`,
   `wp/mu-plugins/headless-cors.php`, and this file
3. Re-verify no collision with `ss -tln | grep :<new-port>`

For one-off showcase screenshots you can run Vite on another port without
changing committed defaults:

```sh
cd spa && npx vite dev --host localhost --port 5185 --strictPort
WCHS_SHOWCASE_SPA_ORIGIN=http://localhost:5185 ./scripts/seed-showcase.sh
```
