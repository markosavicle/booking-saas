# Shared by both server blocks in default.conf. Not named *.conf, so nginx doesn't load it on its own.
index index.php index.html;
# Livewire file uploads; keep in step with docker/php/uploads.ini post_max_size.
client_max_body_size 10M;
root /var/www/html/public;

location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ \.php$ {
    # Resolve `app` per request via Docker's DNS: a rebuilt app container gets a new IP,
    # and a static upstream would 502 until nginx itself restarted.
    resolver 127.0.0.11 valid=10s ipv6=off;
    set $php_upstream app:9000;
    fastcgi_pass $php_upstream;
    fastcgi_index index.php;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    include fastcgi_params;
    # Replace whatever the client sent; Laravel trusts these (bootstrap/app.php).
    # Each server block sets $client_ip / $client_proto to what it can vouch for.
    fastcgi_param HTTP_X_FORWARDED_FOR $client_ip if_not_empty;
    fastcgi_param HTTP_X_FORWARDED_PROTO $client_proto if_not_empty;
    fastcgi_param HTTP_X_FORWARDED_HOST "" if_not_empty;
    fastcgi_param HTTP_X_FORWARDED_PORT "" if_not_empty;
    fastcgi_param HTTP_X_FORWARDED_PREFIX "" if_not_empty;
}

location ~ /\.ht {
    deny all;
}
