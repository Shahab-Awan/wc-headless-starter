#!/bin/bash
# Usage: ./set-mode.sh <mode>
# Sets the WCHS access mode (0=maintenance, 1=locked, 2=browse-only, 3=open)
MODE=${1:-3}
docker exec wchs-wpcli wp eval "\$s=\\WCHS\\Admin\\AdminPage::get_site_settings();\$s['access_mode']=$MODE;update_option('wchs_site_settings',\$s);wp_cache_flush();echo \"mode=$MODE\";" 2>/dev/null | grep "mode="
