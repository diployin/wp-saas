<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SiteController extends Controller
{
    public function createGuestSite(Request $request)
    {
        // Generate unique lowercase subdomain
        $subdomain = $this->generateUniqueSubdomain();

        $wp_admin_user = 'admin';
        $wp_admin_password = Str::random(12);

        $dockerNetwork = 'wp-saas-net';
        $mysql_root_password = 'rootpass';

        // Start MySQL container
        exec("docker run -d --rm --name mysql_{$subdomain} --network {$dockerNetwork} -e MYSQL_ROOT_PASSWORD={$mysql_root_password} -e MYSQL_DATABASE=wp_{$subdomain} mysql:5.7");

        // Wait for MySQL to initialize before starting WordPress
        sleep(15);

        // Start WordPress container with Traefik labels for routing
        exec("docker run -d --rm --name wp_{$subdomain} --network {$dockerNetwork} -e WORDPRESS_DB_HOST=mysql_{$subdomain}:3306 -e WORDPRESS_DB_USER=root -e WORDPRESS_DB_PASSWORD={$mysql_root_password} -e WORDPRESS_DB_NAME=wp_{$subdomain} -e WORDPRESS_TABLE_PREFIX=wp_ -l traefik.enable=true -l 'traefik.http.routers.wp_{$subdomain}.rule=Host(`{$subdomain}.wpsaas.localhost`)' -l 'traefik.http.services.wp_{$subdomain}.loadbalancer.server.port=80' wordpress:latest");

        return response()->json([
            'url' => "http://{$subdomain}.wpsaas.localhost",
            'admin_user' => $wp_admin_user,
            'admin_password' => $wp_admin_password,
        ]);
    }

    private function generateUniqueSubdomain()
    {
        $adjectives = ['funny', 'happy', 'crazy', 'silent', 'lazy'];
        $animals = ['lion', 'tiger', 'bear', 'fox', 'panda'];

        $adj = $adjectives[array_rand($adjectives)];
        $animal = $animals[array_rand($animals)];

        // Return lowercase subdomain to avoid Docker naming issues
        return strtolower($adj . '-' . $animal . '-' . Str::random(4));
    }
}
