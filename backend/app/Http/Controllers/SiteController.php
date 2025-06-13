<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SiteController extends Controller
{
    public function createGuestSite(Request $request)
    {
        set_time_limit(300); // Increase max execution time

        $domain = config('wp_domain.domain', 'wpsaas.localhost');
        $subdomain = $this->generateUniqueSubdomain();

        $wp_admin_user = 'admin';
        $wp_admin_password = Str::random(12);

        $dockerNetwork = 'wp-saas-net';
        $mysql_root_password = 'rootpass';

        // Start MySQL container
        exec("docker run -d --rm --platform linux/amd64 --name mysql_{$subdomain} --network {$dockerNetwork} "
            . "-e MYSQL_ROOT_PASSWORD={$mysql_root_password} "
            . "-e MYSQL_DATABASE=wp_{$subdomain} "
            . "mysql:5.7");

        sleep(15);

        // Prepare the Host rule for Traefik, safely escaped
        $hostRule = "Host(`{$subdomain}.{$domain}`)";
        $hostRuleEscaped = escapeshellarg($hostRule);

        // Start WordPress container with Traefik labels
        $wpRunCmd = "docker run -d --rm --platform linux/amd64 --name wp_{$subdomain} --network {$dockerNetwork} "
            . "-e WORDPRESS_DB_HOST=mysql_{$subdomain}:3306 "
            . "-e WORDPRESS_DB_USER=root "
            . "-e WORDPRESS_DB_PASSWORD={$mysql_root_password} "
            . "-e WORDPRESS_DB_NAME=wp_{$subdomain} "
            . "-e WORDPRESS_TABLE_PREFIX=wp_ "
            . "-l traefik.enable=true "
            . "-l traefik.http.routers.wp_{$subdomain}.rule={$hostRuleEscaped} "
            . "-l traefik.http.routers.wp_{$subdomain}.entrypoints=websecure "
            . "-l traefik.http.routers.wp_{$subdomain}.tls.certresolver=letsencrypt "
            . "-l traefik.http.services.wp_{$subdomain}.loadbalancer.server.port=80 "
            . "wordpress:latest";

        exec($wpRunCmd);

        sleep(20);

        // Run WP-CLI install inside the container
        $installCmd = "docker exec wp_{$subdomain} wp core install "
            . "--url='https://{$subdomain}.{$domain}' "
            . "--title='WP SaaS Site' "
            . "--admin_user='{$wp_admin_user}' "
            . "--admin_password='{$wp_admin_password}' "
            . "--admin_email='admin@{$domain}' "
            . "--skip-email "
            . "--path='/var/www/html'";

        exec($installCmd);

        return response()->json([
            'url' => "https://{$subdomain}.{$domain}",
            'login_url' => "https://{$subdomain}.{$domain}/wp-login.php",
            'admin_user' => $wp_admin_user,
            'admin_password' => $wp_admin_password,
        ], 200, [], JSON_UNESCAPED_SLASHES);
    }

    private function generateUniqueSubdomain()
    {
        $adjectives = ['funny', 'happy', 'crazy', 'silent', 'lazy'];
        $animals = ['lion', 'tiger', 'bear', 'fox', 'panda'];

        $adj = $adjectives[array_rand($adjectives)];
        $animal = $animals[array_rand($animals)];

        return strtolower($adj . '-' . $animal . '-' . Str::random(4));
    }
}
